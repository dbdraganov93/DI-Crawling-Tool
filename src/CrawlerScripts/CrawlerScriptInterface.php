<?php
// src/CrawlerScripts/CrawlerScriptInterface.php

namespace App\CrawlerScripts;

interface CrawlerScriptInterface
{
    /**
     * Execute the crawler script.
     *
     * @param int $companyId
     * @return void
     */
    public function crawl(int $companyId): void;
}
