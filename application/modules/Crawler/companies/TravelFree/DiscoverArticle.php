<?php

/**
 * Discover Crawler für TravelFree (ID: 70960, 73550)
 */

class Crawler_Company_TravelFree_DiscoverArticle extends Crawler_Generic_Company
{
    private const FTP_IMAGES_FOLDER = 'images';
    private const FTP_DEFAULT_COMPANY = 70960;

    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private int $companyId;

    public function crawl($companyId)
    {
        $this->companyId = $companyId;
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $campaignData = $googleSpreadsheet->getCustomerData('TravelFree');

        $localFiles = $this->getFilesFromFtp($campaignData['file_name']);

        $articles = new Marktjagd_Collection_Api_Article();
        $articlesData = $spreadsheetService->readFile($localFiles['articles'], TRUE)->getElement(0)->getData();

        foreach ($articlesData as $articleDetails) {
            if (!is_numeric($articleDetails['Page'])) {
                continue;
            }

            $articleData = $this->getArticleData($articleDetails, $localFiles['images'], $campaignData);
            if (!$articleData) {
                continue;
            }

            $article = $this->createArticle($articleData);
            $articles->addElement($article, true, 'complex', false);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getFilesFromFtp(string $fileName): array
    {
        $this->ftp->connect(self::FTP_DEFAULT_COMPANY);
        $localPath = $this->ftp->generateLocalDownloadFolder(self::FTP_DEFAULT_COMPANY);

        $files = [
            'articles' => '',
            'images' => []
        ];

        foreach ($this->ftp->listFiles() as $singleFile) {
            if (preg_match('#' . $fileName . '\.zip$#i', $singleFile, $matches)) {
                $archive = $this->ftp->downloadFtpToDir($singleFile, $localPath);
                $files['images'] = $this->extractImages($archive, $localPath . self::FTP_IMAGES_FOLDER . '/');
            } elseif (preg_match('#' . $fileName . '\.xlsx$#i', $singleFile, $matches)) {
                $files['articles'] = $this->ftp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        $this->ftp->close();

        if (!$archive) {
            throw new Exception('Company ID: ' . $this->companyId . ': Can\'t find image archive ' . $fileName . '.zip');
        }

        if (!$files['articles']) {
            throw new Exception('Company ID: ' . $this->companyId . ': Can\'t find articles table ' . $fileName . '.xlsx');
        }


        return $files;
    }

    private function extractImages(string $localArchive, string $pathToImages): array
    {
        $sArchive = new Marktjagd_Service_Input_Archive();

        if (!$sArchive->unzip($localArchive, $pathToImages)) {
            throw new Exception('Unable to extract archive ' . $localArchive);
        }

        return $this->getImages($pathToImages);
    }

    private function getImages(string $pathToImages): array
    {
        $images = [];
        foreach (scandir($pathToImages) as $singleFile) {
            if (substr($singleFile, 0, 1) == '.') {
                continue;
            }

            if (is_dir($pathToImages . $singleFile)) {
                $images = array_replace($images, $this->getImages($pathToImages . $singleFile . '/'));
                continue;
            }

            if (preg_match('#\.png|\.jpg#', $singleFile)) {
                $imageId = preg_split('#\.#', $singleFile)[0];
                $images[$imageId] = $this->ftp->generatePublicFtpUrl($pathToImages . $singleFile);
            }
        }

        return $images;
    }

    private function getArticleData(array $articleDetails, array $images, array $campaignData): array
    {
        $articleImage = $this->getArticleImage($articleDetails, $images);
        if (!$articleImage) {
            $this->_logger->err('Company ID: ' . $this->companyId . ': Image not found for article: ' . $articleDetails['ID']);
            return [];
        }

        $dateParts = explode('.', $campaignData['start_date']);
        $discoverIdentifier = "DISC_{$dateParts[0]}{$dateParts[1]}_";

        return [
            'number' => $discoverIdentifier . $articleDetails['ID'],
            'title' => $articleDetails['Name'],
            'text' => $articleDetails['Description'],
            'price' => $articleDetails['Final Price'],
            'suggestedRetailPrice' => $articleDetails['List Price'] ?? NULL,
            'image' => $articleImage,
            'url' => $articleDetails['Clickout URL'],
            'start' => $campaignData['start_date'],
            'end' => $campaignData['end_date'],
        ];
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setArticleNumber($articleData['number'])
            ->setTitle($articleData['title'])
            ->setText($articleData['text'])
            ->setPrice($articleData['price'])
            ->setSuggestedRetailPrice($articleData['suggestedRetailPrice'])
            ->setImage($articleData['image'])
            ->setUrl($articleData['url'])
            ->setStart($articleData['start'])
            ->setEnd($articleData['end'])
            ->setVisibleStart($article->getStart());
    }

    private function getArticleImage(array $articleData, array $images): string
    {
        $imageId = preg_replace('#\/#', '', $articleData['Image Link']);
        if (!$imageId) {
            $imageId = preg_replace('#\/#', '', $articleData['ID']);
        }

        return $images[$imageId] ?? '';
    }
}
