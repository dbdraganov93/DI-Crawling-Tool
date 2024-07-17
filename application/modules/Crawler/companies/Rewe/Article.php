<?php

/**
 * Artikel Crawler für Rewe (ID: 23)
 */
class Crawler_Company_Rewe_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page(true);
        $cArticles = new Marktjagd_Collection_Api_Article();        
        $feedUrl = 'https://shop.rewe.de/feeds/angebote';    
        
        $priceMatch = array('#EUR#', '#\.#', '#\s*#');
        $priceReplace = array('', ',', '');        
        
        $sPage->open($feedUrl);        
        $xml = simplexml_load_string($sPage->getPage()->getResponseBody());

        foreach ($xml->entry as $entry) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $namespaces = $entry->getNameSpaces(true);
            $g = $entry->children($namespaces['g']);
            
            $eArticle->setArticleNumber((string) $g->id)
                    ->setTitle((string) $g->title)
                    ->setText((string) $g->description)
                    //->setUrl((string) $g->link)
                    ->setImage((string) $g->image_link)
                    ->setTrademark((string) $g->brand)
                    ->setEan((string) $g->gtin)
                    ->setArticleNumberManufacturer((string) $g->mpn)
                    ->setStart((string) $g->availability_date)
                    ->setEnd((string) $g->expiration_date)
                    ->setSuggestedRetailPrice(preg_replace($priceMatch, $priceReplace, (string) $g->price))
                    ->setPrice(preg_replace($priceMatch, $priceReplace, (string) $g->sale_price));
                    //->setShipping(preg_replace($priceMatch, $priceReplace, (string) $g->shipping->price) . ' €');
            
                    /*        
                    if (strlen((string) $g->additional_image_link) > 5) {                        
                        $eArticle->setImage($eArticle->getImage() . ',' . (string) $g->additional_image_link);
                    }                     
                     */                    

            $eArticle->setTags(preg_replace('#\s*>\s*#', ',', $g->product_type[1]));                                    
            $eArticle->setText(preg_replace('#\*#', '', $eArticle->getText()));                    
                    
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}

