<?php

/* 
 * Artikel Crawler für Schuhpark (ID: 29143)
 */

class Crawler_Company_Schuhpark_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.schuhpark.de/';
        $searchUrl = $baseUrl . 'service/schuhpflege';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<img[^>]*src="([^"]+?schuhpflegeanleitung\/[^"]+?)"[^>]*>.+?<h3[^>]*>\s*([^<]+?)\s*</h3>\s*<p[^>]*>\s*([^<]+?)\s*<#';
        if (!preg_match_all($pattern, $page, $articleMatches))
        {
            throw new Exception ($companyId . ': unable to get any articles.');
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        for ($i = 0; $i < count($articleMatches[0]); $i++)
        {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle($articleMatches[2][$i])
                    ->setImage($baseUrl . $articleMatches[1][$i])
                    ->setText($articleMatches[3][$i]);
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}