<?php

/**
 * Discover Crawler für Ernstings Family (ID: 22133)
 */

class Crawler_Company_Ernstings_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # Upload the article csv file onto our FTP server (folder 'Discover')   #
        #                                                                       #
        # adjust articleFile                                                    #
        #########################################################################

        $articleFile = 'articles_archive.csv';
//        $articleFile = 'articles_wogibtswas_at.csv';
        $articleMapping = 'Artikelliste_April Prospekt_20220411.xlsx';
        $skipThisManyLines = 4;
        $howManyTabsCategories = 3; // 2 tabs
        $startDate = '08.04.2022';
        $trackParam = '&cmp_name=Apr-prospekt_Feed-Format';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();


        $localFolder = $sFtp->connect(22133, TRUE);
        $localArticleFile = $sFtp->downloadFtpToDir('./' . $articleFile , $localFolder);
        $productsMapping = $sFtp->downloadFtpToDir('./Discover/' . $articleMapping  , $localFolder);

        $aData = $sPss->readFile($localArticleFile, true)->getElement(0)->getData();

        $productsAndCategories = [];
        for ($i = 0; $i <= $howManyTabsCategories; $i++) {
            $mappingsTabCat = $sPss->readFile($productsMapping)->getElement($i)->getData();
            var_dump($mappingsTabCat);

            foreach ($mappingsTabCat as $key => $row) {
                if ($key <= $skipThisManyLines || empty($row[2])) {
                    continue;
                }

                $productsAndCategories[$row[2]] = $row[2];
            }
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            if (!array_key_exists($singleRow['PRODUCT_ID'], $productsAndCategories)) {
                print_r('no match found');
                continue;
            }

            if (!preg_match(
                '#(?<date>\d{2}\.\d{2})\.\d{4,5}$#',
                $this->decodeExcelDate($singleRow['PRODUCT_AVAILABILITYDATE']),
                $dateMatch
            )) {
                throw new Exception('No end dates were found for this product -> ' . $singleRow['PRODUCT_NAME']);
            }

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['PRODUCT_ID'])
                ->setTitle($singleRow['PRODUCT_NAME'])
                ->setText($singleRow['PRODUCT_SHORTDESC'])
                ->setPrice($singleRow['PRODUCT_PRICE'])
                //->setSuggestedRetailPrice($singleRow['suggested_retail_price'])
                //->setArticleNumberManufacturer($singleRow['article_number_manufacturer'])
                ->setImage($singleRow['PRODUCT_FULLIMAGE'])
                ->setUrl('https://www.handelsangebote.de/fl/22133-ernsting-s-family-filialen')
                //->setNational($singleRow['national'])
                ->setStart($startDate)
                ->setEnd($dateMatch['date'] . '.2022')
                ->setVisibleStart($eArticle->getStart())
                ->setVisibleEnd($eArticle->getEnd())
            ;

            $cArticles->addElement($eArticle,TRUE, 'complex', FALSE);

        }

        return $this->getResponse($cArticles, $companyId);
    }

    public function decodeExcelDate($dateExcel, $datePattern = 'd.m.Y')
    {
        $unixDate = ($dateExcel - 25569) * 86400;

        return gmdate($datePattern, $unixDate);
    }

//    public function getProductDetails($productID)
//    {
//        $sPage = new Marktjagd_Service_Input_Page();
//        $url = "https://www.ernstings-family.de/SearchDisplay?langId=-3&storeId=10151&catalogId=10051&searchTerm=$productID&pageSize=24&page_cat1=Suchergebnisse";
//        $sPage->open($url);
//        $sPage->getPage()->getResponseBody();
//        preg_match('#https://www.ernstings-family.de/gesucht.*;8197410295.*[^"]#', $sPage, $detailPageUrl);
//        var_dump($detailPageUrl);
//        //https://www.ernstings-family.de/SearchDisplay?langId=-3&storeId=10151&catalogId=10051&searchTerm=8197410295&pageSize=24&page_cat1=Suchergebnisse
//        //https://www.ernstings-family.de/gesucht.*;8197410295.*[^"]
//    }
}
