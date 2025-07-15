<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\KernelInterface;

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
    ) {
        $this->projectDir = $kernel->getProjectDir();
    }

    /**
     * Process a brochure PDF and return information about detected products.
     *
     * @param string $pdfPath Path to uploaded brochure
     *
     * @return array{annotated:string,json:string,data:array} paths to files and data
     */
    public function process(string $pdfPath): array
    {
        $pages = $this->extractText($pdfPath);
        $allText = '';
        foreach ($pages as $p) {
            $allText .= $p['text'] . "\n";
        }

        $meta = $this->detectCompany($allText);
        $products = $this->detectProducts($pages);
        $products = $this->enrichProducts($products, $meta['website'] ?? '');

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

        $linkedDir = $this->projectDir . '/pdf';
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
        foreach ($products as &$p) {
            $query = trim(sprintf('site:%s %s', $website, $p['product']));
            $url = sprintf(
                'https://www.googleapis.com/customsearch/v1?key=%s&cx=%s&q=%s',
                $this->googleApiKey,
                $this->googleCx,
                urlencode($query)
            );
            try {
                $resp = $this->httpClient->request('GET', $url);
                $data = $resp->toArray(false);
                $p['url'] = $data['items'][0]['link'] ?? null;
            } catch (\Throwable) {
                $p['url'] = null;
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
