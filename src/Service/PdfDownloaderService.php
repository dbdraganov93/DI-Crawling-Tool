<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PdfDownloaderService
{
    private string $projectDir;
    private HttpClientInterface $httpClient;

    public function __construct(KernelInterface $kernel, HttpClientInterface $httpClient)
    {
        $this->projectDir = $kernel->getProjectDir();
        $this->httpClient = $httpClient;
    }

    /**
     * Downloads a PDF file from the given URL and saves it to public/pdf.
     *
     * @param string $url The full URL of the PDF file.
     * @return string The full path to the downloaded file.
     * @throws \Exception if download fails.
     */
    public function download(string $url, string $apiKey): string
    {
        $destinationDir = $this->projectDir . '/public/pdf';

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));
        $destinationPath = $destinationDir . '/' . $filename;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'x-api-key' => $apiKey,
                ],
            ]);
            $content = $response->getContent();
            file_put_contents($destinationPath, $content);
        } catch (\Throwable $e) {
            throw new \Exception("Download failed: " . $e->getMessage());
        }

        return $destinationPath;
    }
}
