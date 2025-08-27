<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Rebuilt brochure linker service.
 *
 * Takes a brochure PDF, detects products via ChatGPT, searches for
 * product links with Google Custom Search and annotates the PDF with
 * the resulting URLs. Also produces a JSON file containing all
 * detected product data.
 */
class BrochureLinkerService
{
    private string $projectDir;
    private string $openaiModel;
    private string $ocrLang;
    private bool $debug;

    public function __construct(
        KernelInterface $kernel,
        private HttpClientInterface $httpClient,
        private PdfLinkAnnotatorService $annotator,
        private LoggerInterface $logger,
        private string $openaiApiKey,
        private string $googleApiKey,
        private string $googleCx,
        ?string $openaiModel = null,
        string $ocrLang = 'eng',
        bool $debug = false,
    ) {
        $this->projectDir = $kernel->getProjectDir();
        $this->openaiModel = $openaiModel ?: 'gpt-4o-mini';
        $this->ocrLang = $ocrLang;
        $this->debug = $debug;
    }

    /**
     * Process the given PDF and return paths to annotated PDF and JSON data.
     *
     * @return array{annotated:string,json:string,data:array}
     */
    public function process(string $pdfPath, string $website, ?string $prefix = '', ?string $suffix = ''): array
    {
        $pages = $this->extractText($pdfPath);
        $products = $this->locateProducts($pages);
        $products = $this->enrichLinks($products, $website, $prefix, $suffix);

        $clickouts = [];
        foreach ($products as $p) {
            if (!isset($p['x'], $p['y'], $p['width'], $p['height'], $p['page'])) {
                continue;
            }
            $clickouts[] = [
                'pageNumber' => (int) $p['page'],
                'x' => (float) $p['x'],
                'y' => (float) $p['y'],
                'width' => (float) $p['width'],
                'height' => (float) $p['height'],
                'url' => $p['url'] ?? '',
            ];
        }

        $linkedDir = $this->projectDir . '/public/pdf';
        if (!is_dir($linkedDir)) {
            mkdir($linkedDir, 0777, true);
        }
        $base = pathinfo($pdfPath, PATHINFO_FILENAME);
        $annotatedPath = sprintf('%s/%s-linked.pdf', $linkedDir, $base);
        $jsonPath = sprintf('%s/%s.json', $linkedDir, $base);

        $this->annotator->annotate($pdfPath, $annotatedPath, $clickouts);
        file_put_contents($jsonPath, json_encode(['products' => $products], JSON_PRETTY_PRINT));

        return [
            'annotated' => $annotatedPath,
            'json' => $jsonPath,
            'data' => ['products' => $products],
        ];
    }

    /**
     * Run Python script to extract text blocks with coordinates.
     *
     * @return array<array{page:int,blocks:array<array{text:string,x:float,y:float,width:float,height:float}>>>|
     */
    private function extractText(string $pdfPath): array
    {
        $script = $this->projectDir . '/scripts/extract_text.py';
        $process = new Process(['python3', $script, $pdfPath, '--lang', $this->ocrLang]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Text extraction failed: ' . $process->getErrorOutput());
        }
        $data = json_decode($process->getOutput(), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Ask ChatGPT to locate products and return their details.
     *
     * @param array $pages Output from extractText
     * @return array<int, array<string,mixed>>
     */
    private function locateProducts(array $pages): array
    {
        $parts = [];
        foreach ($pages as $page) {
            $text = implode(' ', array_column($page['blocks'], 'text'));
            $parts[] = sprintf('Page %d:\n%s', $page['page'], $text);
        }
        $prompt = 'Given the following brochure pages, extract products and return a JSON array. '
            . 'Each product must have keys: page, title, price, discount_price, category, size, sku, '
            . 'x, y, width, height (coordinates are between 0 and 1 with origin at top-left).'
            . "\n\n" . implode("\n\n", $parts);
        $response = $this->chatGpt($prompt);
        $products = $this->decodeJson($response);
        return is_array($products) ? $products : [];
    }

    /**
     * For each product perform a Google Custom Search to find a link.
     */
    private function enrichLinks(array $products, string $website, ?string $prefix, ?string $suffix): array
    {
        foreach ($products as &$p) {
            $query = sprintf('site:%s %s', $website, $p['title'] ?? '');
            $url = $this->googleFirstResult($query);
            if ($url) {
                $p['url'] = ($prefix ?? '') . $url . ($suffix ?? '');
            }
        }
        unset($p);
        return $products;
    }

    private function googleFirstResult(string $query): ?string
    {
        if (!$this->googleApiKey || !$this->googleCx) {
            return null;
        }
        try {
            if ($this->debug) {
                $this->logger->debug('Google search query', ['query' => $query]);
            }
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/customsearch/v1', [
                'query' => [
                    'key' => $this->googleApiKey,
                    'cx' => $this->googleCx,
                    'q' => $query,
                ],
            ]);
            $raw = $response->getContent(false);
            if ($this->debug) {
                $this->logger->debug('Google raw response', ['body' => $raw]);
            }
            $data = json_decode($raw, true);
            $link = $data['items'][0]['link'] ?? null;
            if ($this->debug) {
                $this->logger->debug('Google first result', ['url' => $link]);
            }
            return $link;
        } catch (\Throwable $e) {
            $this->logger->error('Google search failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function chatGpt(string $prompt): string
    {
        $prompt = $this->sanitizeUtf8($prompt);
        if (empty($this->openaiApiKey)) {
            throw new \RuntimeException('OpenAI API key not configured');
        }
        try {
            if ($this->debug) {
                $this->logger->debug('ChatGPT prompt', ['prompt' => $prompt]);
            }
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                ],
                'json' => [
                    'model' => $this->openaiModel,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                ],
            ]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
            if ($this->debug) {
                $this->logger->debug('ChatGPT raw response', ['status' => $status, 'body' => $body]);
            }
            if ($status !== 200) {
                throw new \RuntimeException('OpenAI API status ' . $status . ': ' . $body);
            }
            $data = json_decode($body, true);
            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI request failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function decodeJson(string $response): array
    {
        $response = trim($response);
        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?|```$/m', '', $response);
        }
        if (preg_match('/\{.*\}/s', $response, $m)) {
            $response = $m[0];
        } elseif (preg_match('/\[.*\]/s', $response, $m)) {
            $response = $m[0];
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }

    private function sanitizeUtf8(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        return iconv('UTF-8', 'UTF-8//IGNORE', $text);
    }
}
