<?php

/*
 * Brochure Crawler für Pflanzen Kölle (ID: 69974)
 */

class Crawler_Company_PflanzenKoelle_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pflanzen-koelle.de/';
        $searchUrl = $baseUrl . 'backend/export/index/pflanzen-koelle_artikel-Marktjagt.csv?feedID=22&hash=8ceeaf4cb853eade1e5c5d6d745c524c';
        $sPage = new Marktjagd_Service_Input_Page();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#([0-9]{10}.+?(\.jpg|\.png))#';
        if (!preg_match_all($pattern, $page, $csvDataMatches))
        {
            throw new Exception($companyId . ': invalid csv.');
        }

        $pattern = '#^(.+?)[0-9]#';
        if (!preg_match($pattern, $page, $keyMatch))
        {
            throw new Exception($companyId . ': invalid csv keys.');
        }

        $aKeys = preg_split('#\s*;\s*#', $keyMatch[1]);
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($csvDataMatches[1] as $singleCsvLine)
        {
            $aData = array_combine($aKeys, str_getcsv($singleCsvLine, ';'));
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($aData['article_number'])
                    ->setTitle($aData['title'])
                    ->setPrice($aData['price'])
                    ->setText($aData['text'])
                    ->setEan($aData['ean'])
                    ->setManufacturer($aData['manufacturer'])
                    ->setTags($aData['tags'])
                    ->setUrl($aData['url'])
                    ->setShipping($aData['shipping'])
                    ->setImage($aData['image']);
            
            if ((float) preg_replace('#,#', '.', $aData['suggested_retail_price']) > (float)preg_replace('#,#', '.', $aData['price']))
            {
                $eArticle->setSuggestedRetailPrice($aData['suggested_retail_price']);
            }
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
