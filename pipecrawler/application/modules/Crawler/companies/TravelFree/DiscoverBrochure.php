<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Travel Free (ID: 70960, 73550)
 */
class Crawler_Company_TravelFree_DiscoverBrochure extends Crawler_Generic_Company
{
    private const FTP_DEFAULT_COMPANY = 70960;

    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private int $companyId;

    public function crawl($companyId)
    {
        $this->companyId = $companyId;
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $campaignData = $googleSpreadsheet->getCustomerData('TravelFree');
        $localFiles = $this->getFilesFromFtp($campaignData['file_name']);

        $brochureData = $this->getBrochureData($localFiles, $campaignData);
        $brochure = $this->createBrochure($brochureData);
        $brochures->addElement($brochure);

        return $this->getResponse($brochures);
    }

    private function getFilesFromFtp(string $fileName): array
    {
        $this->ftp->connect(self::FTP_DEFAULT_COMPANY);
        $localPath = $this->ftp->generateLocalDownloadFolder(self::FTP_DEFAULT_COMPANY);

        $files = [
            'brochure' => '',
            'articles' => '',
        ];

        foreach ($this->ftp->listFiles() as $singleFile) {
            if (preg_match('#'.$fileName.'\.pdf$#i', $singleFile, $matches)) {
                $files['brochure'] = $this->ftp->downloadFtpToDir($singleFile, $localPath);
            }
            elseif (preg_match('#'.$fileName.'\.xlsx$#i', $singleFile, $matches)) {
                $files['articles'] = $this->ftp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        if (!$files['brochure']) {
            throw new Exception('Company ID: ' . $this->companyId . ': Can\'t find brochure ' . $fileName . '.pdf');
        }

        if (!$files['articles']) {
            throw new Exception('Company ID: ' . $this->companyId . ': Can\'t find articles table ' . $fileName . '.xlsx');
        }

        $this->ftp->close();

        return $files;
    }

    private function getBrochureData(array $files, array $campaignData): array
    {
        $dateParts = explode('.', $campaignData['start_date']);
        $discoverIdentifier = "DISC_{$dateParts[0]}{$dateParts[1]}_";

        return [
            'title' => 'Aktionsangebote',
            'number' => 'Travel_Free_' . $campaignData['start_date'],
            'url' => $files['brochure'],
            'start' => $campaignData['start_date'],
            'end' => $campaignData['end_date'],
            'visible_start' => $campaignData['start_date'],
            'layout' => $this->generateLayout($this->companyId, $files['articles'], $discoverIdentifier),
            'variety' => 'leaflet',
        ];
    }

    private function generateLayout(int $companyId, string $articlesFile, string $discoverIdentifier): string
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $articles = $spreadsheetService->readFile($articlesFile, TRUE)->getElement(0)->getData();

        $activeArticles = $api->getActiveArticleCollection($companyId)->getElements();
        $articleIds = [];
        foreach ($activeArticles as $activeArticle) {
            $articleIds[$activeArticle->getArticleNumber()] = $activeArticle->getArticleId();
        }

        $pages = [];
        foreach ($articles as $article) {
            // we skip the first few rows where thy have added comments.
            if (!is_numeric($article['Page'])) {
                continue;
            }

            if (!$articleIds[$discoverIdentifier . $article['ID']]) {
                $this->_logger->err('fehlende Artikelnummer ' . $article['ID']);
                continue;
            }

            $pages[$article['Page']]['products'][] = [
                'priority' => $article['Layout Priority'],
                'product_id' => $articleIds[$discoverIdentifier . $article['ID']]
            ];
            $pages[$article['Page']]['page_metaphor'] = $article['Page'];
        }

        ksort($pages);

        $response = Blender::blendApi($companyId, $pages);
        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('Company ID: ' . $this->companyId . ': blender api did not work out');
        }

        return $response['body'];
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($brochureData['title'])
            ->setBrochureNumber($brochureData['number'])
            ->setUrl($brochureData['url'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visible_start'])
            ->setLayout($brochureData['layout'])
            ->setVariety($brochureData['variety']);
    }
}
