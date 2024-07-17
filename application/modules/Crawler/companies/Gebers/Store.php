<?php

/*71758
 * Store Crawler für Gebers (ID: 69957)
 */

class Crawler_Company_Gebers_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.gebers.de/';
        $searchUrl = $baseUrl . 'fachgeschaefte/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
               
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();                                                                         
                
        if (!preg_match_all('#<area[^>]*alt="([^"]+)"#', $page, $storeMatches)) {
            throw new Exception('unable to get any stores.');
        }           
               
        foreach ($storeMatches[1] as $idx => $storeMatch) {           
            $eStore = new Marktjagd_Entity_Api_Store();
                              
            if (preg_match('#<p>\s*<strong>(.+?)</strong>\s*</p>\s*<p>(.+?)</p>#', $storeMatch, $addressMatch)){
                $addressLines = preg_split('#<br[^>]*>#', $addressMatch[1]);
                
                $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                        ->setCity($sAddress->extractAddressPart('city', $addressLines[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                        ->setPhone($sAddress->normalizePhoneNumber($addressLines[2]))
                        ->setFax($sAddress->normalizePhoneNumber($addressLines[3]));

                $eStore->setStoreHours($sTimes->generateMjOpenings($addressMatch[2]));
            } else {
                $this->_logger->err('cannot get address from ' . $storeMatch);
            }           
                        
            $cStores->addElement($eStore);
        }                                    
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
