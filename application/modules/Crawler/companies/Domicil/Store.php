<?php

/*7117
 * Store Crawler für Domicil (ID: 71746)
 */

class Crawler_Company_Domicil_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.domicil.de/';        
        $searchUrl = $baseUrl . '/einrichtungshauser/';        
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
               
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();                                                         
        
        if (!preg_match_all('#Domicil Einrichtungshaus\s*<a[^>]*href="(http://domicil.de/[^"]+)"#', $page, $storeMatches)) {
            throw new Exception('unable to get any stores.');
        }           
                
        foreach ($storeMatches[1] as $storeLink) {           
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $sPage->open($storeLink);
            $page = $sPage->getPage()->getResponseBody();                                                                            
            
            $eStore->setWebsite($storeLink);
            
            if (preg_match('#<p>(.+?)<br[^>]*(>.+?Deutschland.+?)</div#', $page, $match)){
                $eStore->setStreetAndStreetNumber($match[1]);
                
                if (preg_match('#>([0-9]{5}[^<]+)<#', $match[2], $submatch)){
                    $eStore->setZipcodeAndCity($submatch[1]);
                }
                
                if (preg_match('#"mailto:([^"]+)"#', $match[2], $submatch)){
                    $eStore->setEmail($submatch[1]);
                }
                
                if (preg_match('#<strong>Tel.+?</strong>(.+?)<#', $match[2], $submatch)){
                    $eStore->setPhoneNormalized($submatch[1]);
                }
                
                if (preg_match('#<strong>Fax.+?</strong>(.+?)<#', $match[2], $submatch)){
                    $eStore->setFaxNormalized($submatch[1]);
                }
                
                if (preg_match('#<p>Öffnungszeiten(.+?)</p>#', $match[2], $submatch)){
                    $eStore->setStoreHoursNormalized($submatch[1]);
                }
            }            
            
            if (preg_match('#72770#', $eStore->getZipcode())) {
                $eStore->setLogo('https://di-gui.marktjagd.de/files/logos/71746/DOM_REU_Logo_Reinzeichnung.png');
            }
            
            $cStores->addElement($eStore);
        }                                    
       
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
