<?php
/**
 * Artikelcrawler für A.T.U (ID: 83)
 */
class Crawler_Company_ATU_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $url = 'http://ws.salesfeeder.com/productGetList.php?merchantId=10016&publisherId=20074&type=.csv';
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
            if (preg_match('#^([0-9\.\,]+)$#', $eArticle->getShipping())) {
                $eArticle->setShipping($eArticle->getShipping() . ' €');
            }
            if (preg_match('#nopic#', $eArticle->getImage())) {
                $eArticle->setImage(NULL);
            }
            $cArticleNew->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticleNew);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}