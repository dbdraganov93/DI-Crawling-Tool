<?php

/**
 * Artikelcrawler für Ernstings Family AT (ID: 72601)
 */

class Crawler_Company_ErnstingsAt_DiscoverArticle extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $cArticles = new Marktjagd_Collection_Api_Article();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $articleFile = 'articles_wogibtswas_at.csv';
        $articleMapping = 'Artikelliste_BKF_20220318.xlsx';
        $skipThisManyLines = 4;
        $howManyTabsCategories = 2; // 3 tabs
        $startDate = date('d.m.Y');
        $trackParam = '&cmp_name=Mrz-BKF_Feed-Format';

        $localFolder = $sFtp->connect('22133', true);
        $localArticleFile = $sFtp->downloadFtpToDir('./' . $articleFile , $localFolder);
        $productsMapping = $sFtp->downloadFtpToDir('./Discover/' . $articleMapping  , $localFolder);

        $aData = $sPss->readFile($localArticleFile, true)->getElement(0)->getData();

        $productsAndCategories = [];
        for ($i = 0; $i <= $howManyTabsCategories; $i++) {
            $mappingsTabCat = $sPss->readFile($productsMapping)->getElement($i)->getData();

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
                ->setImage($singleRow['PRODUCT_FULLIMAGE'])
                ->setUrl($singleRow['PRODUCT_URL'] . $trackParam)
                ->setStart($startDate)
                ->setEnd($dateMatch['date']  . '.' . date('Y'))
                ->setVisibleStart($eArticle->getStart())
                ->setVisibleEnd($eArticle->getEnd())
            ;

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    public function decodeExcelDate($dateExcel, $datePattern = 'd.m.Y')
    {
        $unixDate = ($dateExcel - 25569) * 86400;

        return gmdate($datePattern, $unixDate);
    }
}
