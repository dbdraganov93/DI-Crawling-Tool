<?php
// src/CrawlerScripts/SampleCrawlerScript.php

namespace App\CrawlerScripts;

use App\Service\FtpService;

class SampleCrawlerScript implements CrawlerScriptInterface
{
    public function crawl(int $companyId, FtpService $ftpService = null): void
    {
        echo "Running crawl for company ID: {$companyId}\n";

        if ($ftpService) {
            $remoteDirectory = '/101';
            $files = $ftpService->listFiles($remoteDirectory);

            echo "Files in directory '{$remoteDirectory}':\n";
            foreach ($files as $file) {
                echo "- {$file['path']}\n";
            }
        } else {
            echo "No FTP operations performed as FtpService was not provided.\n";
        }
    }
}
