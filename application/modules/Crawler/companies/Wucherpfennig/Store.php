<?php

/**
 * Store Crawler für Wucherpfennig (ID: 71729)
 */
class Crawler_Company_Wucherpfennig_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.wucherpfennig.de/';
        
        $overviewUrl = $baseUrl . 'wp/rental';
        $searchUrl = $baseUrl . 'wp/rental/getStationInfo?code=';
                        
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
       
        $page = $sPage->getPage();
        $page->setUseCookies(true);
        $client = $page->getClient();
        $client->setHeaders('Accept', 'application/json, text/javascript, */*; q=0.01');
        $client->setHeaders('Referer', 'https://www.wucherpfennig.de/wp/rental/');
        $client->setHeaders('X-Requested-With', 'XMLHttpRequest');
        $page->setClient($client);
        $sPage->setPage($page);
                
        $cStores = new Marktjagd_Collection_Api_Store();        
                
        $sPage->open($overviewUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<div[^>]*id="blockSelStartStation"(.+?)</div>\s*</div>#is', $page, $selectMatch)){
            throw new Exception('cannot find any station links on ' . $overviewUrl);            
        }
        
        if (!preg_match_all('#<div[^>]*id="([^"]+)"[^>]*data-station-info#', $selectMatch[1], $stationsMatch)){
            throw new Exception('cannot find any station links on ' . $overviewUrl);
        }
        
        foreach ($stationsMatch[1] as $station){
            $this->_logger->info('open ' . $searchUrl . $station);
            $sPage->open($searchUrl . $station);
            $json = $sPage->getPage()->getResponseAsJson();
   
            $eStore = new Marktjagd_Entity_Api_Store;
            
            $eStore->setStoreNumber($json->code)
                    ->setCity($json->city)
                    ->setZipcode($json->zipcode)
                    ->setStreet($sAddress->extractAddressPart('street', $json->street))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $json->street))                                                          
                    ->setWebsite($json->url);
                        
            if (strlen($json->phone) > 5){
                $eStore->setPhone($json->phone);
            }
            
            if (strlen($json->fax) > 5){
                $eStore->setFax($json->fax);
            }
            
            if (strlen($json->email) > 5){
                $eStore->setEmail($json->email);
            }
                        
            if ($json->shedules && is_array($json->shedules)){
                $storeHours = array();            
                foreach ($json->shedules as $dayTime){
                    if ($dayTime->btime && $dayTime->btime != '00:00'){
                        if ($dayTime->mbtime && $dayTime->mbtime != '00:00'){
                            $storeHours[] = $dayTime->day . ' ' . $dayTime->btime . '-' . $dayTime->mbtime;
                            $storeHours[] = $dayTime->day . ' ' . $dayTime->metime . '-' . $dayTime->etime;
                        } else {
                            $storeHours[] = $dayTime->day . ' ' . $dayTime->btime . '-' . $dayTime->etime;
                        }
                    }
                }            
                $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $storeHours)));
            }
                        
            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
    
    function remove_utf8_bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }    
}
