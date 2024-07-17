<?php

/**
 * Artikel Crawler für denn's Biomarkt (ID: 29068)
 */
class Crawler_Company_DennsBiomarkt_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.denns-biomarkt.de';
        $searchUrl = $baseUrl . '/angebote/?eID=apertoSearchResults&type=offer&offset=0&limit=500&usermarket=&offerperiodstate=current&q=';
        $sPage = new Marktjagd_Service_Input_Page();
        $cArticles = new Marktjagd_Collection_Api_Article();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json->results->resultDocuments as $jsonArticle) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle($jsonArticle->title);
            $eArticle->setPrice($jsonArticle->pricePrefix . '.' . $jsonArticle->priceSuffix);
            $eArticle->setSuggestedRetailPrice($jsonArticle->priceB);
            $eArticle->setText($jsonArticle->shortDescription);
            $eArticle->setManufacturer($jsonArticle->brand);
            $eArticle->setUrl(urldecode($jsonArticle->sharingLink));
            $eArticle->setArticleNumberManufacturer($jsonArticle->articleNumber);
            $eArticle->setArticleNumber($jsonArticle->productUid);

            if (count($jsonArticle->img->srcSet)) {
                $imgUrl = end($jsonArticle->img->srcSet)->src;
                if (substr($imgUrl, 0, 4) != 'http') {
                    $imgUrl = $baseUrl .'/' . $imgUrl;
                }

                $localFile = preg_replace('#.+\/([^\/]+)$#', '$1', $imgUrl);
                $localFile = preg_replace('#\?.+?$#', '', $localFile);
                $localFile = preg_replace('#\.top#', '', $localFile);
                if (!in_array($localFile, scandir($localPath))) {
                    exec('wget --referer="https://46.4.39.83/" -O ' . $localPath . $localFile . ' ' . $imgUrl);
                }

                $imageUrl = $sHttp->generatePublicHttpUrl($localPath . $localFile);
                $eArticle->setImage($imageUrl);
            }
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}