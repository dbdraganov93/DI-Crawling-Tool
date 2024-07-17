<?php

/*7117
 * Store Crawler für Whos Persfect (ID: 71758)
 */

class Crawler_Company_WhosPerfect_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.whos-perfect.de/';
       
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
               
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();                                                         
        
        if (!preg_match_all('#<div[^>]*id="footer_filialanzeige_([\d]+)"[^>]*>\s*<p>(.+?)</p>.*?<p>(.+?)</p>#', $page, $storeMatches)) {
            throw new Exception('unable to get any stores.');
        }           
               
        foreach ($storeMatches[2] as $idx => $storeMatch) {           
            $eStore = new Marktjagd_Entity_Api_Store();
                              
            $eStore->setStoreNumber($storeMatches[1][$idx])
                    ->setStoreHours($sTimes->generateMjOpenings(str_replace('von', ':', $storeMatches[3][$idx])));
            
            if (strpos($storeMatches[3][$idx], 'Probewohnen')){
                $eStore->setStoreHoursNotes(trim(preg_replace('#<[^>]*>#', ' ', substr($storeMatches[3][$idx], strpos($storeMatches[3][$idx], 'Probewohnen')))));
            }                        
            
            if (preg_match('#([A-Z].+?)<br[^>]*>([^<]+)<br[^>]*>([^<]+$)#', $storeMatch, $addressMatch)){
                $eStore->setStreet($sAddress->extractAddressPart('street', $addressMatch[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressMatch[1]))
                        ->setCity($sAddress->extractAddressPart('city', $addressMatch[2]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[2]))
                        ->setPhone($sAddress->normalizePhoneNumber($addressMatch[3]));
            }
                        
            $cStores->addElement($eStore);
        }                                    

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
