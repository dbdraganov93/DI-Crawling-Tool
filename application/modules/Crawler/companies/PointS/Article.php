<?php
/**
 * Artikelcrawler für point S (ID: 28672)
 */
class Crawler_Company_PointS_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $url = 'ftp://marktjagd:jaegerundsammler@ftp.netrapid.de/artikel.xml';
        $cArticle = new Marktjagd_Collection_Api_Article();

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadFolder = $sHttp->generateLocalDownloadFolder($companyId);

        $sDownload = new Marktjagd_Service_Transfer_Download();
        $filePath = $sDownload->downloadByUrl($url, $downloadFolder);
        $xml = simplexml_load_file($filePath);

        // Eingabedatei zeilenweise durchgehen und gruppieren:
        foreach ($xml->article as $article) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($article->article_number)
                     ->setTitle($article->title)
                     ->setPrice($article->price)
                     ->setText($article->text)
                     ->setEan($article->ean)
                     ->setManufacturer($article->manufacturer)
                     ->setArticleNumberManufacturer($article->article_number_manufacturer)
                     ->setTrademark($article->trademark)
                     ->setTags($article->tags)
                     ->setSize($article->size)
                     ->setAmount($article->amount)
                     ->setUrl($article->url)
                     ->setImage($article->image);
            $cArticle->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}