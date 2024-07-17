<?php

/**
 * Artikel Crawler für Primark (ID: 67698)
 */
class Crawler_Company_Primark_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.primark.com';
        $jsonUrl = $baseUrl . '/de/news/index?under=&page=';
        $sPage = new Marktjagd_Service_Input_Page();
                             
        $page = $sPage->getPage();
        $client = $page->getClient();
        $client->setHeaders('Accept', 'application/json');
        $page->setClient($client);
        $sPage->setPage($page);       
        
        $cArticles = new Marktjagd_Collection_Api_Article();        
        
        $articleAvailable = true;
        $pageNumber = 1;
        while ($articleAvailable) {
            //echo $jsonUrl . $pageNumber . "\n";
            $sPage->open($jsonUrl . $pageNumber);
            $json = $sPage->getPage()->getResponseAsJson();
            
            if (!count($json->Items)){
                $articleAvailable = false;
                continue;
            }
            
            foreach ($json->Items as $articleItem) {            
                // nicht vor Darstellung aufnehmen
                if (preg_match('#Date\(([^\)]+)\)#', $articleItem->VisibleFrom, $dateMatch)){
                    if (time() < substr($dateMatch[1], 0, 10)){
                        continue;
                    }
                } else {
                    throw new exception($companyId . 'unable to find valid date in json');
                }

                if (!strlen($articleItem->Price)){
                    continue;
                }
                
                $eArticle = new Marktjagd_Entity_Api_Article();
                $titlePrefix = '';
                if ($articleItem->DepartmentWomen){
                     $titlePrefix = "Abteilung: Damen";
                } elseif ($articleItem->DepartmentMen){
                    $titlePrefix = "Abteilung: Herren";
                } elseif ($articleItem->DepartmentKids){
                    $titlePrefix = "Abteilung: Kinder";                    
                }

                $eArticle->setArticleNumber($articleItem->Id)
                        ->setTitle($articleItem->Name)
                        ->setText($articleItem->Description)
                        ->setPrice(str_replace('.', ',', (string) $articleItem->Price))
                        ->setImage('http:' . $articleItem->BigImagePath);

                if (strlen($titlePrefix)){
                    $eArticle->setText($eArticle->getText() . '<br /><br />' . $titlePrefix);
                }
                
                $cArticles->addElement($eArticle);
            }
            
            $pageNumber++;
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
