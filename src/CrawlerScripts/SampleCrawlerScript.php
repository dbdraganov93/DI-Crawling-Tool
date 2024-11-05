<?php
// src/CrawlerScripts/SampleCrawlerScript.php

namespace App\CrawlerScripts;

class SampleCrawlerScript implements CrawlerScriptInterface
{
    public function crawl(int $companyId): void
    {
        echo "Running crawl for company ID: {$companyId}";
        // Additional crawling logic here
    }
}
