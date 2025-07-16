<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;

/**
 * Service that enriches uploaded brochure PDFs with product links.
 */
class BrochureLinkerService
{
    private string $projectDir;

    public function __construct(
        KernelInterface $kernel,
        private HttpClientInterface $httpClient,
        private PdfLinkAnnotatorService $annotator,
        private string $openaiApiKey,
        private string $googleApiKey,
        private string $googleCx,
        private LoggerInterface $logger,
    ) {
        $this->projectDir = $kernel->getProjectDir();
    }

    /**
     * Process a brochure PDF and return information about detected products.
     *
     * @param string $pdfPath Path to uploaded brochure
     * @param string|null $website Optional website override for product search
     *
     * @return array{annotated:string,json:string,data:array} paths to files and data
     */
    public function process(string $pdfPath, ?string $website = null): array
    {
        $this->logger->info('Starting brochure processing', ['pdf' => $pdfPath]);
        $pages = $this->extractText($pdfPath);
        $allText = '';
        foreach ($pages as $p) {
            $allText .= $p['text'] . "\n";
        }

        $meta = $this->detectCompany($allText);
        $products = $this->detectProducts($pages);
        $searchWebsite = $website ?: ($meta['website'] ?? '');
        if ($website) {
            $meta['website'] = $website;
        }
        $products = $this->enrichProducts($products, $searchWebsite);

        $clickouts = [];
        foreach ($products as $p) {
            $clickouts[] = [
                'pageNumber' => $p['page'],
                'x' => $p['position']['x'] ?? 0.8,
                'y' => $p['position']['y'] ?? 0.05,
                'width' => $p['position']['width'] ?? 0.15,
                'height' => $p['position']['height'] ?? 0.05,
                'url' => $p['url'] ?? '',
            ];
        }

        // store linked brochures under the public/pdf directory so they are
        // accessible via the web server
        $linkedDir = $this->projectDir . '/public/pdf';
        if (!is_dir($linkedDir)) {
            mkdir($linkedDir, 0777, true);
        }
        $base = pathinfo($pdfPath, PATHINFO_FILENAME);
        $annotatedPath = sprintf('%s/%s-linked.pdf', $linkedDir, $base);
        $jsonPath = sprintf('%s/%s.json', $linkedDir, $base);

        $this->annotator->annotate($pdfPath, $annotatedPath, $clickouts);
        file_put_contents($jsonPath, json_encode([
            'meta' => $meta,
            'products' => $products,
        ], JSON_PRETTY_PRINT));

        $this->logger->info('Brochure processed', [
            'annotated' => $annotatedPath,
            'json' => $jsonPath,
        ]);

        return [
            'annotated' => $annotatedPath,
            'json' => $jsonPath,
            'data' => ['meta' => $meta, 'products' => $products],
        ];
    }

    /**
     * Run Python OCR script on the PDF.
     *
     * @return array<array{page:int,text:string}>
     */
    private function extractText(string $pdfPath): array
    {
        $script = $this->projectDir . '/scripts/extract_text.py';
        $process = new Process(['python3', $script, $pdfPath]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Text extraction failed: ' . $process->getErrorOutput());
        }

        $data = json_decode($process->getOutput(), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Detect company, country and website using ChatGPT.
     */
    private function detectCompany(string $text): array
    {
        $prompt = "Extract the retailer/company name, country code and official website from the following brochure text. Return JSON with keys company, country and website.";
        $response = $this->chatGpt($prompt . "\n" . $text);
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Detect products per page using ChatGPT.
     *
     * @param array<array{page:int,text:string}> $pages
     */
    private function detectProducts(array $pages): array
    {
        $products = [];
        foreach ($pages as $page) {
            $prompt = sprintf(
                "List all products from this brochure page. Provide JSON array of objects with keys page and product. Text:\n%s",
                substr($page['text'], 0, 2000)
            );
            $res = $this->chatGpt($prompt);
            $pageProducts = json_decode($res, true);
            if (is_array($pageProducts)) {
                foreach ($pageProducts as $p) {
                    $p['page'] = $page['page'];
                    $products[] = $p;
                }
            }
        }
        return $products;
    }

    /**
     * Add Google search links for each product.
     */
    private function enrichProducts(array $products, string $website): array
    {
        if (empty($this->googleApiKey) || empty($this->googleCx)) {
            throw new \RuntimeException('Google search credentials not configured');
        }

        $domain = parse_url($website, PHP_URL_HOST) ?: $website;
        $domain = preg_replace('/^www\./', '', $domain);

        foreach ($products as &$p) {
            $query = trim(sprintf('site:%s %s', $domain, $p['product']));
            $url = sprintf(
                'https://www.googleapis.com/customsearch/v1?key=%s&cx=%s&q=%s',
                $this->googleApiKey,
                $this->googleCx,
                urlencode($query)
            );

            $this->logger->info('Searching product', ['query' => $query]);

            try {
                $resp = $this->httpClient->request('GET', $url);
                $status = $resp->getStatusCode();
                $this->logger->info('Google response', ['status' => $status]);

                if ($status !== 200) {
                    $body = $resp->getContent(false);
                    $this->logger->error('Google API non-200', ['body' => $body]);
                    throw new \RuntimeException('Google API status ' . $status);
                }

                $data = $resp->toArray(false);

                if (isset($data['error'])) {
                    $this->logger->error('Google API error', ['response' => $data]);
                    throw new \RuntimeException('Google API error: ' . ($data['error']['message'] ?? 'unknown'));
                }

                if (isset($data['items'][0]['link'])) {
                    $p['url'] = $data['items'][0]['link'];
                } else {
                    $this->logger->warning('No search results', ['query' => $query, 'response' => $data]);
                    $p['url'] = null;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Search failed', ['query' => $query, 'error' => $e->getMessage()]);
                throw new \RuntimeException('Google search failed: ' . $e->getMessage());
            }
        }

        return $products;
    }

    private function chatGpt(string $prompt): string
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ],
        ]);
        $data = $response->toArray(false);
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
