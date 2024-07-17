<?php
/**
 * Artikelcrawler für Amazon.de 70890
 */
class Crawler_Company_Amazon_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $searchArray = array(
            array('Apparel' => 'Mode'),
            array('Apparel' => 'Kleid'),
            array('Beauty' => 'Drogerie'),
            array('Beauty' => 'Schmuck'),
            array('Grocery' => 'Getränke'),
            array('Grocery' => 'Bio'),
            array('Grocery' => 'Trinken'),
            array('Shoes' => 'Schuh'),
            array('Jewelry' => 'Schmuck'),
            array('Shoes' => 'Schuh'),
            array('Watches' => 'Uhr')
            );
                
        $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/amazon.ini');
              
        $amazon = new Zend_Service_Amazon(
                    $config->mjAffiliate->appId,
                    $config->mjAffiliate->countryCode,
                    $config->mjAffiliate->secretKey
                );
                                
        $associateTag = $config->mjAffiliate->associateTag;
        
        $cArticle = new Marktjagd_Collection_Api_Article();                
        
        foreach ($searchArray as $searchFilter){
            $searchIndex = key($searchFilter);
            $keywords = reset($searchFilter);        
            
            $page = 1;        
            do {                
                $params = array(
                        'ItemPage' => $page,
                        'SearchIndex' => $searchIndex,
                        'Keywords' => $keywords,
                        'AssociateTag' => $associateTag,   // wird bei Linkgemnerierung berücksichtigt, Identifizierung des Partners
                        'Sort' => 'salesrank',             // Sortierung nach Verkaufsrang (beste zuerst)                        
                        'ResponseGroup' => 'Medium',       // Werte, welche zurückgegeben werden sollen (Mittel: u.a. inkl. Bilder, Preis)
                        'Availability' => 'Available',     // nur verfügbare Artikel
                        'MerchantId' => 'Amazon'           // nur Artikel, die von amazon angeboten werden
                    );

                $this->_logger->log("search for $searchIndex ($keywords), page $page", Zend_Log::INFO);
                
                for ($tries = 1; $tries <=5; $tries++){
                    try {                 
                        // 1 Sekunde warten, 503er wird sonst schnell provoziert
                        usleep(1000 * 1000);
                        $results = $amazon->itemSearch($params);        
                        break;
                    } catch (Exception $ex) {                    
                        $this->_logger->log('error while request itemSearch (try again): ' . $ex->getMessage(), Zend_Log::ERR);
                        continue;
                    }                    
                    $this->_logger->log('error while request itemSearch (abort): ' . $ex->getMessage(), Zend_Log::CRIT);
                    return $this->_response->generateResponseByFileName(false);
                }
        
                foreach ($results as $result) {            
                    $eArticle = new Marktjagd_Entity_Api_Article();            
                                
                    // nur Artikel aufnehmen, die von amazon selbst angeboten werden            
                    $eArticle->setArticleNumber($result->ASIN)
                             ->setTitle($result->Title)
                             ->setUrl($result->DetailPageURL)
                             ->setImage($result->LargeImage->Url)                   
                             ->setManufacturer($result->Manufacturer)
                             ->setColor($result->Color)
                             ->setArticleNumberManufacturer($result->MPN)
                             ->setEan($result->EAN);

                    // wenn keine grosse Variante gefunden, dann die mittlere verwenden                    
                    if (!$result->LargeImage->Url){
                        $eArticle->setImage($result->MediumImage->Url);
                    }                    

                    if ($result->Offers->LowestNewPrice){
                        $eArticle->setPrice('ab ' . str_replace('.',',',($result->Offers->LowestNewPrice)/100));
                    }
                    
                    if ($result->Feature && is_array($result->Feature)){
                        $eArticle->addText('<ul>');
                            foreach($result->Feature as $featureItem){
                                $eArticle->addText('<li>' . $featureItem . '</li>');
                            }
                        $eArticle->addText('</ul>');
                    }
                    
                    $cArticle->addElement($eArticle);                        
                }
                $page++;
            } while ($page <= $results->totalPages() && $page <= 10);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);           
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
