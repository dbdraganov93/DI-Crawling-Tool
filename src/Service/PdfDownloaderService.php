<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

class PdfDownloaderService
{
    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
    }

    /**
     * Downloads a PDF file from the given URL and saves it to public/pdf.
     *
     * @param string $url The full URL of the PDF file.
     * @return string The full path to the downloaded file.
     * @throws \Exception if download fails.
     */
    public function download(string $url): string
    {
        $destinationDir = $this->projectDir . '/public/pdf';

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));
        $destinationPath = $destinationDir . '/' . $filename;

        $fileContent = file_get_contents($url);
        if ($fileContent === false) {
            throw new \Exception("Failed to download file from URL: $url");
        }

        file_put_contents($destinationPath, $fileContent);

        return $destinationPath;
    }
}
