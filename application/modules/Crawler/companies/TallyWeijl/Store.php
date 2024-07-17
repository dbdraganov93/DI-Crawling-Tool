<?php

/**
 * Store Crawler für TallyWeijl (ID: 67863)
 */
class Crawler_Company_TallyWeijl_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.tally-weijl.com';
              
        $storesUrl = $baseUrl . '/webapp/wcs/stores/servlet/StoreLocator?searchAction=getStores&country=DE&city=&clickAndCollect=&ignoreNotLocalized=true';
        $detailUrl = $baseUrl . '/webapp/wcs/stores/servlet/StoreLocatorDetailsView?storeIds=';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $page = $sPage->getPage();
        $page->setUseCookies(true);
        $sPage->setPage($page);
                
        // open base-URL to get cookie
        $sPage->open($baseUrl);        
        
        $sPage->open($storesUrl);
        $jsonStores = $sPage->getPage()->getResponseAsJson();
                
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jsonStores->stores as $jsonStore){
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($jsonStore->storeId)
                    ->setLatitude((string) $jsonStore->latitude)
                    ->setLongitude((string) $jsonStore->longitude);                       
            
            $sPage->open($detailUrl . $jsonStore->storeId);
            $detailPage = $sPage->getPage()->getResponseBody(); 
            
            if (preg_match('#<span[^>]*class="store-address"[^>]*>(.+?)</span>#', $detailPage, $match)){                               
                $eStore->setStreet($sAddress->extractAddressPart('street', $match[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $match[1]));
            }
            
            if (preg_match('#<span[^>]*class="store-city"[^>]*>(.+?)</span>#', $detailPage, $match)){                               
                $eStore->setCity($sAddress->extractAddressPart('city', $match[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $match[1]));
            }
            
            if (preg_match('#<span[^>]*class="store-phone"[^>]*>(.+?)</span>#', $detailPage, $match)){                               
                $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
            }

            if (preg_match('#<ul[^>]*class="[^"]*store-hours[^"]*"[^>]*>(.+?)</ul>#', $detailPage, $match)){
                $eStore->setStoreHours($sTimes->generateMjOpenings($match[1]));
            }

            $cStores->addElement($eStore);
        }
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}