<?php

/**
 * Artikel Crawler für MFO Matratzen (ID: 72108)
 */
class Crawler_Company_Mfo_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $feedUrl = 'https://get.cpexp.de/7j8boQnspcJgGi0qF_Xx7zt9OxzRD42CjAOiRPNj8-EVjzd6Gz-D8O58SVeDD79S/mfomatratzen_googleshoppingde.csv';

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sHttp->getRemoteFile($feedUrl, $localPath);

        $sCsv = new Marktjagd_Service_Input_GoogleProducts();
        $cArticles = $sCsv->generateMjCollection($localPath . 'mfomatratzen_googleshoppingde.csv');

        $cArticlesCleaned = new Marktjagd_Collection_Api_Article();
        foreach ($cArticles->getElements() as $element) {
            /* @var $element Marktjagd_Entity_Api_Article */

            $element->setPrice(str_replace(
                array('DE:::', ' EUR', '.'),
                array('', '', ','),
                $element->getPrice()));
            $element->setShipping(str_replace(
                array('DE:::', ' EUR', '.'),
                array('', '', ','),
                $element->getShipping()));

            $element->setStoreNumber('id:1164575,id:1135762,8452,id:1164574');
            $cArticlesCleaned->addElement($element);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticlesCleaned);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}