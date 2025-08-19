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
    private string $openaiModel;
    private string $ocrLang;

    public function __construct(
        KernelInterface $kernel,
        private HttpClientInterface $httpClient,
        private PdfLinkAnnotatorService $annotator,
        private string $openaiApiKey,
        private string $googleApiKey,
        private string $googleCx,
        private LoggerInterface $logger,
        ?string $openaiModel = null,
        string $ocrLang = 'eng',
    ) {
        $this->projectDir = $kernel->getProjectDir();
        $this->openaiModel = $openaiModel ?: 'gpt-3.5-turbo';
        $this->ocrLang = $ocrLang;
    }

    /**
     * Process a brochure PDF and return information about detected products.
     *
     * @param string      $pdfPath Path to uploaded brochure
     * @param string|null $website Optional website override for product search
     * @param string|null $prefix  Optional prefix to prepend to each link
     * @param string|null $suffix  Optional suffix to append to each link
     *
     * @return array{annotated:string,json:string,data:array} paths to files and data
     */
    public function process(string $pdfPath, ?string $website = null, ?string $prefix = null, ?string $suffix = null): array
    {
        $this->logger->info('Starting brochure processing', ['pdf' => $pdfPath]);
        $pages = $this->extractText($pdfPath);
        $allText = '';
        foreach ($pages as &$p) {
            $p['text'] = implode(' ', array_column($p['blocks'], 'text'));
            $allText .= $p['text'] . "\n";
        }
        unset($p);

        $meta = $this->detectCompany($allText);
        $products = $this->detectProducts($pages);
        $searchWebsite = $website ?: ($meta['website'] ?? '');
        if ($website) {
            $meta['website'] = $website;
        }
        $products = $this->enrichProducts($products, $searchWebsite);

        $clickouts = [];
        foreach ($products as &$p) {
            $finalUrl = $p['url'] ?? '';
            if ($finalUrl !== '') {
                $finalUrl = ($prefix ?? '') . $finalUrl . ($suffix ?? '');
            }
            $p['url'] = $finalUrl;
            $clickouts[] = [
                'pageNumber' => $p['page'],
                'x' => $p['position']['x'] ?? 0.8,
                'y' => $p['position']['y'] ?? 0.05,
                'width' => $p['position']['width'] ?? 0.15,
                'height' => $p['position']['height'] ?? 0.05,
                'url' => $finalUrl,
            ];
        }
        unset($p);

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
     * @return array<array{
     *     page:int,
     *     blocks:array<array{
     *         text:string,
     *         x:float,
     *         y:float,
     *         width:float,
     *         height:float
     *     }>
     * }>
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
     * Detect company, country and website using ChatGPT.
     */
    private function detectCompany(string $text): array
    {
        $prompt = "Extract the retailer/company name, country code and official website from the following brochure text. Respond only with JSON object {\"company\":\"\",\"country\":\"\",\"website\":\"\"}.";
        $response = $this->chatGpt($prompt . "\n" . $text);
        $data = $this->decodeJson($response);
        return $data;
    }

    /**
     * Detect products per page using ChatGPT.
     *
     * @param array<array{
     *     page:int,
     *     text:string,
     *     blocks:array<array{
     *         text:string,
     *         x:float,
     *         y:float,
     *         width:float,
     *         height:float
     *     }>
     * }> $pages
     */
    private function detectProducts(array $pages): array
    {
        $products = [];
        foreach ($pages as $page) {
            $prompt = sprintf(
                "From the following brochure page text extract product names. Respond only with a JSON array of objects having keys `page` and `product`. Text:\n%s",
                substr($page['text'], 0, 2000)
            );
            $res = $this->chatGpt($prompt);
            $pageProducts = $this->decodeJson($res);

            if (isset($pageProducts['products']) && is_array($pageProducts['products'])) {
                $pageProducts = $pageProducts['products'];
            }

            if (is_array($pageProducts)) {
                foreach ($pageProducts as $p) {
                    if (!is_array($p)) {
                        continue;
                    }

                    $name = $p['product'] ?? $p['name'] ?? null;
                    if (empty($name)) {
                        continue;
                    }

                    $p['product'] = $name;
                    $p['page'] = $page['page'];
                    $p['position'] = $this->findPosition($page['blocks'], $name);
                    $products[] = $p;
                }
            }
        }
        return $products;
    }

    /**
     * Attempt to find a bounding box for the given product name within the page blocks.
     *
     * @param array<array{text:string,x:float,y:float,width:float,height:float}> $blocks
     */
    private function findPosition(array $blocks, string $product): ?array
    {
        $needle = mb_strtolower($product);
        $needleTokens = array_values(array_filter(preg_split('/\s+/', $needle)));

        $best = null;
        $bestScore = 0.0;

        foreach ($blocks as $b) {
            $hay = mb_strtolower($b['text']);

            // direct substring match
            if (str_contains($hay, $needle)) {
                return [
                    'x' => $b['x'],
                    'y' => $b['y'],
                    'width' => $b['width'],
                    'height' => $b['height'],
                ];
            }

            // compute token intersection score
            $hayTokens = array_values(array_filter(preg_split('/\s+/', $hay)));
            if (empty($hayTokens)) {
                continue;
            }

            $intersection = array_intersect($needleTokens, $hayTokens);
            $score = count($intersection) / count($needleTokens);

            // also consider overall similarity
            similar_text($needle, $hay, $similarity);
            $score = max($score, $similarity / 100);

            // fuzzy score ignoring spaces and hyphens
            $score = max($score, $this->fuzzyScore($needle, $hay));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $b;
            }

            if ($score >= 0.8) {
                break; // good enough
            }
        }

        if ($best && $bestScore >= 0.5) {
            return [
                'x' => $best['x'],
                'y' => $best['y'],
                'width' => $best['width'],
                'height' => $best['height'],
            ];
        }

        // Fallback: union of matches for individual tokens
        $matches = [];
        foreach ($needleTokens as $token) {
            foreach ($blocks as $b) {
                if (str_contains(mb_strtolower($b['text']), $token)) {
                    $matches[] = $b;
                    break;
                }
            }
        }

        if ($matches) {
            $minX = $minY = 1.0;
            $maxX = $maxY = 0.0;
            foreach ($matches as $m) {
                $minX = min($minX, $m['x']);
                $minY = min($minY, $m['y']);
                $maxX = max($maxX, $m['x'] + $m['width']);
                $maxY = max($maxY, $m['y'] + $m['height']);
            }

            return [
                'x' => $minX,
                'y' => $minY,
                'width' => $maxX - $minX,
                'height' => $maxY - $minY,
            ];
        }

        return null;
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
            $nameForSearch = $this->sanitizeProductName($p['product']);
            $query = trim(sprintf('site:%s %s', $domain, $nameForSearch));
            $url = sprintf(
                'https://www.googleapis.com/customsearch/v1?key=%s&cx=%s&q=%s',
                $this->googleApiKey,
                $this->googleCx,
                urlencode($query)
            );

            $this->logger->info('Searching product', ['query' => $query]);

            $attempt = 0;
            while ($attempt < 3) {
                try {
                    $resp = $this->httpClient->request('GET', $url);
                    $status = $resp->getStatusCode();
                    $this->logger->info('Google response', [
                        'status' => $status,
                        'attempt' => $attempt + 1,
                    ]);

                    if ($status !== 200) {
                        $body = $resp->getContent(false);
                        $this->logger->error('Google API non-200', [
                            'status' => $status,
                            'body' => $body,
                        ]);
                        if (in_array($status, [403, 429], true)) {
                            throw new \RuntimeException('Google API quota exhausted');
                        }
                        if ($status >= 500 && $attempt < 2) {
                            $attempt++;
                            sleep(1);
                            continue;
                        }
                        throw new \RuntimeException('Google API status ' . $status);
                    }

                    $data = $resp->toArray(false);

                    if (isset($data['error'])) {
                        $this->logger->error('Google API error', ['response' => $data]);
                        $message = $data['error']['message'] ?? 'unknown';
                        $reason = $data['error']['errors'][0]['reason'] ?? '';
                        $code = $data['error']['code'] ?? 0;
                        if (in_array($code, [403, 429], true) || $reason === 'dailyLimitExceeded' || str_contains(strtolower($message), 'quota')) {
                            throw new \RuntimeException('Google API quota exhausted');
                        }
                        throw new \RuntimeException('Google API error: ' . $message);
                    }

                    $p['url'] = null;
                    $p['details'] = '';
                    if (!empty($data['items'])) {
                        $bestItem = null;
                        $bestScore = 0.0;
                        foreach ($data['items'] as $item) {
                            $text = strtolower(($item['title'] ?? '') . ' ' . ($item['snippet'] ?? ''));
                            similar_text(strtolower($nameForSearch), $text, $score);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestItem = $item;
                            }
                        }

                        if ($bestItem) {
                            $p['url'] = $bestItem['link'] ?? null;
                            $p['details'] = $bestItem['snippet'] ?? '';
                        } else {
                            $p['url'] = $data['items'][0]['link'] ?? null;
                            $p['details'] = $data['items'][0]['snippet'] ?? '';
                        }
                    } else {
                        $this->logger->warning('No search results', [
                            'query' => $query,
                            'response' => $data,
                        ]);
                    }

                    break; // success
                } catch (\Throwable $e) {
                    $this->logger->warning('Search attempt failed', [
                        'query' => $query,
                        'error' => $e->getMessage(),
                        'attempt' => $attempt + 1,
                    ]);
                    if ($attempt < 2) {
                        $attempt++;
                        sleep(1);
                        continue;
                    }
                    $this->logger->error('Search failed', [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException('Google search failed: ' . $e->getMessage());
                }
            }
        }

        return $products;
    }

    /**
     * Remove price information from the product name so Google search
     * focuses on the actual product description.
     */
    private function sanitizeProductName(string $name): string
    {
        // Drop currency symbols
        $name = str_replace(['€', 'EUR', 'eur', 'euro', 'EURO'], '', $name);
        // Remove typical price patterns like "1.99" or "3,49"
        $name = preg_replace('/\d+[,.]\d{1,2}/', '', $name);
        // Collapse whitespace
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Levenshtein-based fuzzy score for partial matches.
     */
    private function fuzzyScore(string $needle, string $hay): float
    {
        $n = preg_replace('/[^a-z0-9]/i', '', $needle);
        $h = preg_replace('/[^a-z0-9]/i', '', $hay);
        $lenN = strlen($n);
        $lenH = strlen($h);
        if ($lenN === 0 || $lenH === 0) {
            return 0.0;
        }
        $best = 0.0;
        if ($lenH >= $lenN) {
            for ($i = 0; $i <= $lenH - $lenN; $i++) {
                $part = substr($h, $i, $lenN);
                $dist = levenshtein($n, $part);
                $best = max($best, 1 - $dist / $lenN);
                if ($best >= 0.9) {
                    break;
                }
            }
        } else {
            $dist = levenshtein($n, $h);
            $best = 1 - $dist / $lenN;
        }
        return $best;
    }

    private function chatGpt(string $prompt): string
    {
        $prompt = $this->sanitizeUtf8($prompt);

        try {
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
            if ($status !== 200) {
                $body = $response->getContent(false);
                $this->logger->error('OpenAI API non-200', [
                    'status' => $status,
                    'body' => $body,
                ]);
                if ($status === 429) {
                    throw new \RuntimeException('OpenAI API quota exhausted');
                }
                throw new \RuntimeException('OpenAI API status ' . $status);
            }

            $data = $response->toArray(false);
            if (isset($data['error'])) {
                $this->logger->error('OpenAI API error', ['response' => $data]);
                $message = $data['error']['message'] ?? 'unknown';
                $type = $data['error']['type'] ?? '';
                if ($type === 'insufficient_quota' || str_contains(strtolower($message), 'quota')) {
                    throw new \RuntimeException('OpenAI API quota exhausted');
                }
                throw new \RuntimeException('OpenAI API error: ' . $message);
            }

            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI request failed', ['error' => $e->getMessage()]);
            if ($e instanceof \RuntimeException) {
                throw $e;
            }

            throw new \RuntimeException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract JSON object or array from an API response that may include code fences.
     */
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

    /**
     * Ensure the given string is valid UTF-8 for JSON encoding.
     */
    private function sanitizeUtf8(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return iconv('UTF-8', 'UTF-8//IGNORE', $text);
    }
}
