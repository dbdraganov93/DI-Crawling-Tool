<?php

/*
 * Brochure Crawler für Forstinger AT (ID: 73469)
 */

class Crawler_Company_ForstingerWgw_DiscoverArticle extends Crawler_Generic_Company
{
    private const DEFAULT_IMAGE = 'https://www.forstinger.com/fileadmin/template/images/logo.png';

    private const ARTICLE_FILE = 'Dezember.xls';

    private const START_DATE = '19.12.2022';
    private const END_DATE = '31.12.2022';
    private const IMAGES_MAIN_DIR = '';
    private const IMAGES_FTP_DIR = '';


    public function crawl($companyId)
    {


        //in case of extra images, create folders in ftp and adjust the variables

        $imagesFullPathDir = self::IMAGES_MAIN_DIR . '/' . self::IMAGES_FTP_DIR;
        $discoverPath = $companyId . '/Dezember';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cArticles = new Marktjagd_Collection_Api_Article();
        $sPage = new Marktjagd_Service_Input_Page();

        $localFolder = $sFtp->connect($discoverPath, true);


        // if images are provided extra
        $sFtp->changedir(self::IMAGES_MAIN_DIR);

        $localArticleFile = $sFtp->downloadFtpToDir('./' . self::ARTICLE_FILE, $localFolder);

        $sFtp->changedir(self::IMAGES_FTP_DIR);
        $imagesIdAndPath = [];
        foreach ($sFtp->listFiles() as $singleImageFile) {
            preg_match('#([^\.]*)(\.jpg)#', $singleImageFile, $match);
            $imagesIdAndPath[$match[1]] = $singleImageFile;
        }

        $sFtp->close();
        $aData = $sPss->readFile($localArticleFile, true)->getElement(0)->getData();

        $productIndex = 0;
        foreach ($aData as $singleRow) {
            if (empty($singleRow['Bezeichnung 1']) && empty($singleRow['Artikelnummer']) && empty($singleRow['URL'])) {
                continue;
            }

            $ftpConfig = $sFtp->getMjFtpConfigNew();

            // resolve image checks the differen types of column name for the image
            switch ($singleRow['BILD URL']) {
                case !empty($singleRow['URL Bild ']):
                    $articleImage = $singleRow['URL Bild '];
                    echo 'found images' . ' ' . $singleRow['URL Bild '] . ' ';
                    break;
                case !empty($singleRow['BILD URL']):
                    $articleImage = $singleRow['BILD URL'];
                    echo 'found images' . ' ' . $singleRow['BILD URL'] . ' ';
                    break;
                default:
                    $this->_logger->alert(
                        ' No image found for this Article: ' . $singleRow['Bezeichnung 1']
                    );
            }

            if (preg_match('#Discover_#', strtolower($articleImage))) {
                continue;
            }

            if (strtolower($articleImage) == strtolower('BILD') || $singleRow[''] == 'Extra Label') {
                $articleImage = null;
            }

            if (array_key_exists($singleRow['Artikelnummer'], $imagesIdAndPath)) {
                $articleImage = 'ftp://' . $ftpConfig['username'] . ':' . $ftpConfig['password'] . '@' . $ftpConfig['hostname'] . '/' . $companyId . '/' . $imagesFullPathDir . '/' . $imagesIdAndPath[$singleRow['Artikelnummer']];
            } elseif (array_key_exists($singleRow['Bezeichnung 1'], $imagesIdAndPath)) {
                $articleImage = 'ftp://' . $ftpConfig['username'] . ':' . $ftpConfig['password'] . '@' . $ftpConfig['hostname'] . '/' . $companyId . '/' . $imagesFullPathDir . '/' . $imagesIdAndPath[$singleRow['Artikelnummer']];
            }

            $url = 'https://www.forstinger.com/out/pictures/generated/product/1/665_665_100/' . $singleRow['Artikelnummer'] . '_1.jpg';
            if (empty($articleImage) && $this->isRemoteImageValid($url)) {
                $this->_logger->info('Image found for this Article: ' . $singleRow['Bezeichnung 1'] . ' searching in: ' . $url);
                $articleImage = $url;
            }

            if (empty($articleImage)) {
                $this->_logger->alert('Will use default image. No image found for this Article: ' . $singleRow['Bezeichnung 1']);
                $articleImage = self::DEFAULT_IMAGE;
            }

            // resolve url
            $url = $singleRow['URL'];
            if (empty($singleRow['URL'])) {
                $url = 'https://www.forstinger.com/';
            }

            // resolve description text
            $description = $singleRow['Bezeichung 2'];
            $webDescription = $this->getDescriptionText($url, $sPage);
            if (!empty($webDescription)) {
                $description = $description . ' - ' . $webDescription;
            }

            // resolve article number
            if (empty($singleRow['Artikelnummer'])) {
                $this->_logger->warn(
                    'No Article number found for this Article: ' . $singleRow['Bezeichnung 1'] .
                    ' Adding special Index: ' . $productIndex
                );
                $articleNumber = 'DISCOVER_' . $productIndex;
                $productIndex++;
            } else {
                $articleNumber = 'DISCOVER_' . $singleRow['Artikelnummer'];
            }

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($articleNumber)
                ->setTitle($singleRow['Bezeichnung 1'])
                ->setText($description)
                ->setImage($articleImage)
                ->setUrl($url)
                ->setStart(self::START_DATE)
                ->setEnd(self::END_DATE)
                ->setVisibleStart($eArticle->getStart())
                ->setPrice($singleRow['Aktionspreis'] ?? $singleRow['Normalpreis'])
                ->setSuggestedRetailPrice($singleRow['Aktionspreis'] ? $singleRow['Normalpreis'] : null);

            if (!empty($singleRow['Extra Label'])) {
                $eArticle->setAdditionalProperties(json_encode(['priceLabel' => trim($singleRow['Extra Label'])]));
            }

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function isRemoteImageValid(string $imageUrl): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        // don't download content
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result !== false) {
            return true;
        } else {
            return false;
        }
    }

    private function getDescriptionText(?string $productUrl, Marktjagd_Service_Input_Page $sPage): ?string
    {
        if (empty($productUrl)) {
            return null;
        }
        $this->_logger->info('Getting data Description Text from: ' . $productUrl);
        $sPage->open($productUrl);
        $page = $sPage->getPage()->getResponseBody();

        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($page);
        libxml_use_internal_errors($old_libxml_error);

        $xpath = new DOMXPath($domDoc);
        $mainNode = $xpath->query('//div[@class="tab-pane fade js-tab-container active in"]');
        if (empty($mainNode->item(0))) {
            return null;
        }

        return $xpath->query(
            $mainNode->item(0)->getNodePath() . '//p'
        )->item(0)->textContent;
    }
}
