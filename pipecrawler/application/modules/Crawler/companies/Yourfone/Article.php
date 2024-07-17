<?php

/**
 * Artikel Crawler für Yourfone (ID: 71740)
 */
class Crawler_Company_Yourfone_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.yourfone.de';
        $searchUrl = $baseUrl . '/configurator/handys';      
        
        $text = '<b>Weitere Informationen zum Angebot erhalten Sie in der Filiale vor Ort.</b><br /><br />';
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        
        $sPage = new Marktjagd_Service_Input_Page();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open main page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
                
        $qArticles = new Zend_Dom_Query($page, 'UTF-8');
        $nArticles = $qArticles->query("div[id*=\"handy-container\"]");
                
        foreach ($nArticles as $nArticle)
        {
            $sArticle = utf8_decode($nArticle->c14n());
            
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setUrl('https://www.yourfone.de/shop-finder')
                    ->setPrice('ab 0,00');
            
            if (preg_match('#<img[^>]*class="handy_img"[^>]*src="([^"]+)">#', $sArticle, $match)){
                $eArticle->setImage($baseUrl . $match[1] . '.png');
            }
            
            if (preg_match('#<h4>(.+?)</h4>#', $sArticle, $match)){
                $eArticle->setTitle(trim($match[1]));
            }
            
            if (preg_match('#<span[^>]*class="handy_data"[^>]*>\s*<hr>(.+?)</hr>#', $sArticle, $match)){
                $eArticle->setText($match[1]);
            }
            
            if (preg_match_all('#<a[^>]*class="farbAuswahlBox[^"]*"[^>]*title="(Farbe:\s*)([^"]+)">#', $sArticle, $match)){
                $eArticle->setColor(implode(', ', array_unique($match[2])));
            }
            
            if (preg_match_all('#<a[^>]*class="auswahlBox[^"]*"[^>]*title="(Speicher:\s*)([^"]+)">#', $sArticle, $match)){
                $eArticle->setSize (implode(', ', array_unique($match[2])));
            }
            
            if (preg_match('#href="(/details[^"]+)"#', $sArticle, $match)){
                $sPage->open($baseUrl . $match[1]);
                $page = $sPage->getPage()->getResponseBody();
                
                if (preg_match('#<th>Hersteller</th>\s*<td>(.+?)</td>#', $page, $match)){
                    $eArticle->setManufacturer(trim($match[1]));
                }
                                
                if (preg_match('#<div[^>]*id="handyshopBilder"[^>]*>(.+?)</div>#', $page, $match)){
                    if (preg_match_all('#<img[^>]*name="(/handyshop/image/[^"]+)"#', $match[1], $submatch)){
                        $images = array();
                        foreach ($submatch[1] as $imageName){
                            $images[] = $baseUrl . $imageName . '.png';
                        }
                    }
                
                    $eArticle->setImage(implode(',', $images));
                }
            }
                        
            $eArticle->setText($text . $eArticle->getText());         
         
            $cArticles->addElement($eArticle);
        }
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}