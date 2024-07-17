<?php
/**
 * Article Crawler for Norma (ID: 106)
 */

class Crawler_Company_Norma_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $url = 'https://productdata.awin.com/datafeed/download/apikey/d106d92b01c908a0312884e4a6ac0a73/language/de/fid/23739/columns/'
            . 'aw_deep_link,product_name,merchant_product_id,merchant_category,description,search_price,category_name,large_image,colour,product_price_old/format/csv/delimiter/%3B/compression/zip/';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sArchive = new Marktjagd_Service_Input_Archive();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $filePath = $sHttp->getRemoteFile($url, $localPath, 'tmp.zip');

        $sArchive->unzip($filePath, $localPath);

        $feedFile = '';
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#\.csv$#', $singleFile)) {
                $feedFile = $localPath . $singleFile;
            }
        }

        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aData = $sPhpSpreadsheet->readFile($feedFile, TRUE, ';')->getElement(0)->getData();

        $count = 0;
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleData) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setUrl($singleData['aw_deep_link'])
                ->setTitle($singleData['product_name'])
                ->setArticleNumber($singleData['merchant_product_id'])
                ->setImage($singleData['large_image'])
                ->setText($singleData['description'])
                ->setPrice($singleData['search_price'])
                ->setColor($singleData['colour'])
                ->setTags(preg_replace('#^([^\/]+)\s*\/\s*(.+)#', '$1', $singleData['merchant_category']))
                ->setSuggestedRetailPrice($singleData['product_price_old'])
                ->setNational(1)
                ->setArticleNumberManufacturer($count++);

            if (!strlen($eArticle->getTags())) {
                $eArticle->setTags($singleData['merchant_category']);
            }

            $cArticles->addElement($eArticle, TRUE, 'simple');
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }
}