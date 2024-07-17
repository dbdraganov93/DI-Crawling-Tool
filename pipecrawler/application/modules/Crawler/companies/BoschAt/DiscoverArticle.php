<?php

/**
 * Discover Article Crawler for Bosch AT (ID: 80219)
 * see https://offerista.slab.com/posts/bosch-at-80219-discover-articles-3zpfkjlv
 */

class Crawler_Company_BoschAt_DiscoverArticle extends Crawler_Generic_Company
{

    private string $localArticleTrackingFile;
    private string $localArticleFile;

    public function crawl($companyId)
    {
        # get the services
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aStores = [
            'P',
            'SB-P',
            'DZ,SCS,W'
        ];

        $aInfos = $sGSRead->getCustomerData('BoschAT');

        $discoverPath = $companyId . '/discover';
        # download the excel files from FTP
        $localPath = $sFtp->connect($discoverPath, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match("#{$aInfos['articleFileName']}$#", $singleFile)) {
                $this->localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }

            if (preg_match("#{$aInfos['trackingListName']}$#", $singleFile)) {
                $this->localArticleTrackingFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        # read the excel files
        $productData = $sPss->readFile($this->localArticleFile, TRUE)->getElement(0)->getData();
        $trackingData = $sPss->readFile($this->localArticleTrackingFile, FALSE)->getElement(0)->getData();

        foreach ($trackingData as $singleRow) {
            if (!strlen($singleRow[17])) {
                continue;
            }

            $aTracking[preg_replace('#[^-]+-#', '', $singleRow[17])] = preg_replace(['#[^"]+"([^"]+)".+#', '#\[timestamp\]#'], ['$1', '%%CACHEBUSTER%%'], $singleRow[18]);

        }

        # get the stores where the products should be displayed
        $cStores = $sApi->findStoresByCompany($companyId);

        $cArticles = new Marktjagd_Collection_Api_Article();

        # add the products once per store (with store suffix)
        foreach ($aStores as $singleStore) {
            foreach ($productData as $singleRow) {
                $eArticle = new Marktjagd_Entity_Api_Article();

                # check the columns as key/value pairs
                # also create the additionalProperties JSON
                $aAdditional = [];
                foreach ($singleRow as $singleAttributeKey => $singleAttributeValue) {
                    if (preg_match('#store#', $singleAttributeKey) && preg_match('#price\sstory\sonly#', $singleAttributeValue)) {
                        $aAdditional['priceLabel'] = 'Bosch Store Rabatt';
                    }

                    if (preg_match('#energy_label$#', $singleAttributeKey) && !preg_match('#n/a#', $singleAttributeValue) && !empty($singleAttributeValue)) {
                        $aAdditional['energyLabel'] = $singleAttributeValue;
                        if (preg_match('#(new|yes)#', $singleRow['energy_label_new'])) {
                            $aAdditional['energyLabelType'] = 'new';
                        } elseif (preg_match('#old#', $singleRow['energy_label_new']) || is_null($singleRow['energy_label_new'])) {
                            $aAdditional['energyLabelType'] = 'old';
                        }
                    }

                    if (!empty($aAdditional)) {
                        $eArticle->setAdditionalProperties(json_encode($aAdditional));
                    }

                    if (preg_match('#(category|page|store|energy_label|energy_label_new|priority)#', $singleAttributeKey)) {
                        continue;
                    }


                    $aSingleAttributeKey = preg_split('#\s*_\s*#', $singleAttributeKey);
                    foreach ($aSingleAttributeKey as &$singlePart) {
                        $singlePart = ucwords($singlePart);
                    }

                    $singleAttributeKey = implode('', $aSingleAttributeKey);
                    $eArticle->{'set' . preg_replace('#bosch\s*#i', '', $singleAttributeKey)}(mb_convert_encoding($singleAttributeValue, 'utf-8', mb_detect_encoding($singleAttributeValue)));
                }
                $eArticle->setStoreNumber($singleStore)
                    ->setArticleNumber($eArticle->getArticleNumber() . '_' . $singleStore . '_Disc')
                    ->setUrl($aTracking[$eArticle->getArticleNumberManufacturer()]);

                $cArticles->addElement($eArticle, FALSE);
            }
        }


        return $this->getResponse($cArticles);
    }
}
