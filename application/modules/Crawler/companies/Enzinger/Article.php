<?php

/**
 * Artikel Crawler für Enziger (ID: 67948)
 */
class Crawler_Company_Enzinger_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page(true);
        $cArticles = new Marktjagd_Collection_Api_Article();        
      
        $feedUrl = 'http://www.enzinger.com/productfeed_idealo-de_idealo.xml';    
        
        $sPage->open($feedUrl);
        $xml = simplexml_load_string($sPage->getPage()->getResponseBody());
        
        foreach ($xml->ARTIKEL as $entry) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $eArticle->setArticleNumber((string) $entry->ARTIKELNUMMER)
                    ->setEan((string) $entry->EAN)
                    ->setArticleNumberManufacturer((string) $feedUrl->HERSTELLERARTIKELNUMMER)
                    ->setManufacturer((string) $entry->HERSTELLERNAME)
                    ->setTitle((string) $entry->PRODUKTNAME)
                    ->setTags(str_replace(' > ', ',', (string) $entry->PRODUKTGRUPPE))
                    ->setPrice(str_replace('.', ',', (string) $entry->PREIS))
                    ->setUrl((string) $entry->PRODUKTURL)
                    ->setText((string) $entry->PRODUKTBESCHREIBUNG);
            
            $images = array();
            foreach ($entry->BILDLINKS->children() as $imglink){
                $images[] = (string) $imglink;
            }
            $eArticle->setImage(implode(',', $images));
                        
            //Zend_Debug::dump($eArticle);
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}

