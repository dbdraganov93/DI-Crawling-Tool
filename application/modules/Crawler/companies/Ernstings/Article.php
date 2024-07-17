<?php

/**
 * Artikelcrawler für erstings family 22133
 */
class Crawler_Company_Ernstings_Article extends Crawler_Generic_Company
{
    private const DATE_FORMAT = 'd.m.Y';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://productdata.awin.com/';
        $feedUrl = $baseUrl . 'datafeed/download/apikey/d106d92b01c908a0312884e4a6ac0a73/language/de/fid/37091/columns/' .
            'aw_deep_link,product_name,aw_product_id,merchant_product_id,merchant_image_url,description,merchant_category,' .
            'search_price,store_price,delivery_cost,merchant_deep_link,colour,product_price_old,large_image,custom_6/format/csv/' .
            'delimiter/%3B/compression/zip/';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localArchive = $sHttp->getRemoteFile($feedUrl, $localPath);

        if (!$sArchive->unzip($localArchive, $localPath)) {
            throw new Exception($companyId . ': unable to decompress archive.');
        }

        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#\.csv$#', $singleFile)) {
                $aData = $sPhpSpreadsheet->readFile($localPath . $singleFile, TRUE, ';')->getElement(0)->getData();
                break;
            }
        }

        $description = array();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleData) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            if (in_array($singleData['description'], $description)) {
                continue;
            }
            $description[] = $singleData['description'];

            $title = $singleData['product_name'];
            if ($singleData['custom_6'] == 'yes') {
                $title = $singleData['product_name'] . ' (Nur online)';
            }

            $eArticle->setTitle($title)
                ->setUrl($singleData['aw_deep_link'])
                ->setArticleNumber($singleData['aw_product_id'])
                ->setArticleNumberManufacturer($singleData['merchant_product_id'])
                ->setImage($singleData['merchant_image_url'])
                ->setText($singleData['description'] . '<br/><br/>' . $singleData['merchant_category'])
                ->setPrice($singleData['search_price'])
                ->setShipping($singleData['delivery_cost'])
                ->setColor($singleData['colour'])
                ->setSuggestedRetailPrice($singleData['product_price_old'])
                ->setStart(date(self::DATE_FORMAT))
                ->setEnd(date(self::DATE_FORMAT, strtotime('+2 days')));

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
