<?php

/*
 * Helpers Class for Hartlauer AT Discover Crawlers (ID: 73468)
 */

class Crawler_Company_HartlauerAt_DiscoverHelpers
{
    public function getCampaignData(): array
    {
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $campaigns = $googleSpreadsheet->getCustomerData('HartlauerAt', 'A1', 'Z', TRUE);

        $now = strtotime('now');
        $campaignsData = [];
        foreach ($campaigns as $campaign) {
            if ($now > strtotime($campaign['end'])) {
                continue;
            }
            if ('FALSE' === $campaign['preview'] && $now < strtotime($campaign['start'] . ' - 1 day')) {
                continue;
            }

            $campaignsData[] = $campaign;
        }

        return $campaignsData;
    }

    public function getArticleNumber(string $number, array $campaignData): string
    {
        return $campaignData['numberPrefix'] . str_replace('.', '', $campaignData['start']) . '_' . $number;
    }

    public function getProductFeedData(string $feedFile, int $companyId): array
    {
        $http = new Marktjagd_Service_Transfer_Http();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localFolder = $http->generateLocalDownloadFolder($companyId);
        $productFeed = $http->getRemoteFile($feedFile, $localFolder);

        return $spreadsheetService->readFile($productFeed, TRUE)->getElement(0)->getData();
    }
}
