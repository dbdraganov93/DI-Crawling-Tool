<?php

/**
 * Helper class for Holz Possling Discover (ID: 71464)
 */

class Crawler_Company_HolzPossling_DiscoverHelpers
{
    public const DEFAULT_FTP_FOLDER = 71464;
    public const CUSTOMER_DATA_TAB = 'holzPosslingGer';

    public function getArticlesFeed(string $feedFile): array
    {
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $articlesFile = $this->getArticlesFile($feedFile);

        $spreadsheetTabs = $spreadsheetService->readFile($articlesFile)->getElements();
        $rawArticleData = [];
        foreach ($spreadsheetTabs as $tab) {
            if (preg_match('#Datenfeed#', $tab->getTitle())) {
                $rawArticleData = $tab->getData();
                break;
            }
        }

        return $this->extractArticlesFeedData($rawArticleData);
    }

    private function getArticlesFile(string $feedFile): string
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $ftp->connect(self::DEFAULT_FTP_FOLDER, TRUE);

        $articlesFile = '';
        foreach ($ftp->listFiles('./discover') as $ftpFile) {
            if (preg_match('#' . $feedFile . '#', $ftpFile)) {
                $articlesFile = $ftp->downloadFtpToDir($ftpFile, $localPath);
                $ftp->close();
                break;
            }
        }

        return $articlesFile;
    }

    private function extractArticlesFeedData(array $rawArticleData): array
    {
        $header = [];
        $articlesFeed = [];

        foreach ($rawArticleData as $rawData) {
            if (!$rawData[2]) {
                continue;
            }
            if (!$header) {
                $header = $rawData;
                continue;
            }

            $articleData = array_combine($header, $rawData);
            $articleData['Produkt'] = preg_replace('#\s#', '', $articleData['Produkt']);
            $articleData['ArtNr'] = preg_replace('#\D#', '', $articleData['ArtNr']);

            $articlesFeed[] = $articleData;
        }

        return $articlesFeed;
    }
}
