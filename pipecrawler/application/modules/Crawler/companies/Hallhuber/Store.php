<?php

/**
 * * Storecrawler für Hallhuber ID: 29081

 *
 * Class Crawler_Company_Hallhuber_Store
 */
class Crawler_Company_Hallhuber_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        
        $domain = 'http://www.hallhuber.com';
        $storeFinderUrl = $domain . '/de/store-finder?radius=1000&measurement=km&latitude=48.137115&longitude=11.576325&address=99099';
           
        $servicePage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();
        
        $servicePage->open($storeFinderUrl);               
        $sPage = $servicePage->getPage()->getResponseBody();
        
        if (!preg_match('#new\s+awStoreLocatorUserMap\(.+?\"items\"\s*\:\s*(\[.+?\])#', $sPage, $jsonMatch)){
            throw new Exception($companyId . ': cannot find any json store information');
        }
        
        $json = json_decode($jsonMatch[1]);

        foreach ($json as $jStore){            
            if ($jStore->country && $jStore->country != "DE"){
                continue;
            }
            
            $aAddress = preg_split('#\s*\|\s*#', $jStore->street);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($jStore->location_id)
                    ->setCity($jStore->city)
                    ->setStreetAndStreetNumber($aAddress[0])
                    ->setZipcode($jStore->zip)
                    ->setPhoneNormalized($jStore->phone)
                    ->setLatitude($jStore->latitude)
                    ->setLongitude($jStore->longtitude)
                    ->setImage($jStore->image)
                    ->setWebsite($jStore->url);
            
            if (array_key_exists(1, $aAddress)) {
                $eStore->setSubtitle($aAddress[1]);
            }
                    
            $cStore->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}