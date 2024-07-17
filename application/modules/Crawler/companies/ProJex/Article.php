<?php
/**
 * Artikelcrawler für Pro-jex (ID: 67254)
 */
class Crawler_Company_ProJex_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $url = 'http://pro-jex.de/export/marktjagd.csv';
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sDownload->downloadByUrl(
            $url,
            $downloadPath);

        $sMjCsv = new Marktjagd_Service_Input_MarktjagdCsv();

        /* @var $cArticle Marktjagd_Collection_Api_Article */
        $cArticle = $sMjCsv->convertToCollection($downloadPathFile, 'articles');

        $cArticleNew = new Marktjagd_Collection_Api_Article();
        foreach ($cArticle->getElements() as $eArticle) {
            /* @var $eArticle Marktjagd_Entity_Api_Article */
            // Falsche Links (teilweise Suchbegriffe) entfernen
            if (!preg_match('#^(http)#is', $eArticle->getUrl())) {
                $eArticle->setUrl('');
            }

            if (!preg_match('#^(http)#is', $eArticle->getImage())) {
                $eArticle->setImage('');
            }
            
            if (preg_match('#^([0-9\.\,]+)$#', $eArticle->getShipping())) {
                $eArticle->setShipping($eArticle->getShipping() . ' €');
            }

            $cArticleNew->addElement($eArticle);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticleNew);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}