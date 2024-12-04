<?php

namespace App\CrawlerScripts;

use Symfony\Component\HttpFoundation\JsonResponse;

interface CrawlerScriptInterface
{
    /**
     * Perform the crawl operation for the given company.
     *
     * @param int $companyId The ID of the company to crawl.
     * @return string A URL for the generated output (e.g., a CSV file).
     */
    public function crawl(int $companyId): ?array;
}
