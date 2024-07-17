<?php

/**
 * Artikel Crawler für Idee und Spiel (ID: 22235)
 */
class Crawler_Company_IdeeUndSpiel_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page(true);
        $cArticles = new Marktjagd_Collection_Api_Article();        
        $feedUrl = 'https://www.ideeundspiel.com/pricelist/2/6b71b3a0-7c53-4b8c-97c2-25caa1cac7fd';    
        
        $priceMatch = array('#\,#', '#\.([0-9]{2})\sEUR#');
        $priceReplace = array('', ',$1');        
                
        $keywordsEurotrain = array (
            'a.c.m.e.',
            'Fleischmann',
            'Rocco',
            'Brawa',
            'Noch',
            'Faller',
            'Trix',
            'Busch',
            'Viessmann',
            'lgb',
            'Märklin',
            'Tillig'
        );
                
        $sPage->open($feedUrl);
        $xml = simplexml_load_string($sPage->getPage()->getResponseBody());
        
        foreach ($xml->channel->item as $entry) {

            $eArticle = new Marktjagd_Entity_Api_Article();
                                    
            $eArticle->setTitle((string) $entry->title)
                    ->setText((string) $entry->description)
                    ->setUrl(str_replace('utm_source=google', 'utm_source=marktjagd', (string) $entry->link));                        
            
            $namespaces = $entry->getNameSpaces(true);
            $g = $entry->children($namespaces['g']);                                    
            
            $eArticle->setArticleNumber((string) $g->id)                                        
                    ->setImage((string) $g->image_link)
                    ->setPrice(preg_replace($priceMatch, $priceReplace, (string) $g->price))
                    ->setTrademark((string) $g->brand)                    
                    ->setEan((string) $g->gtin)
                    ->setArticleNumberManufacturer((string) $g->mpn);
         
            $eArticle->setText('Das dargestellte Angebot gilt beim idee+spiel Onlineshop (zzgl. Versandkosten). In den idee+spiel Filialen kann der Preis abweichen. Bitte erfragen Sie dort die Verfügbarkeit. Vielen Dank für Ihr Verständnis.<br /><br />' . $eArticle->getText());            
            
            $eArticle->setText(preg_replace('#new\s+Tip\([^\)]+\)#is', '', $eArticle->getText()));
            $eArticle->setText(preg_replace('#new\s+Tip\([^\)]+$#is', '', $eArticle->getText()));
        
            $eArticle->setDistribution('Idee+Spiel');
            
            foreach ($keywordsEurotrain as $keywordEurotrain){
                if (stripos($eArticle->getTitle(), $keywordEurotrain) !== false && stripos($eArticle->getTitle(), $keywordEurotrain) == 0){
                    $eArticle->setDistribution('Eurotrain');                                        
                    break;
                }
            }            
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}

