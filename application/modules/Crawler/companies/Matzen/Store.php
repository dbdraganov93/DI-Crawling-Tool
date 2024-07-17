<?php

/**
 * Store Crawler für Matzen Kaufhäuser (ID: 71357)
 */
class Crawler_Company_Matzen_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.matzen-mo.de/';
        $searchUrl = $baseUrl . 'oeffnungszeiten.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<p[^>]*class="p-Text"[^>]*>(MATZEN.+?)</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $pattern = '#<p[^>]*class="p-Text"[^>]*>\s*(.+?)\s*<br[^>]*>\s*(.+?)\s*<br[^>]*>\s*(.+?)\s*</p>\s*</div>#s';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                throw new Exception($companyId . ': unable to get store address.');
            }
            
            $pattern = '#Öffnungszeiten(.+?)</p>\s*</div#is';
            if (preg_match($pattern, $singleStore, $timeMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#\/\s*Feiertag\s*#', '', $timeMatch[1])));
            }
            
            $pattern = '#Tel(.+?)<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#Fax(.+?)<#';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $eStore->setSubtitle($addressMatch[1])
                    ->setStreet($sAddress->extractAddressPart('street', $addressMatch[2]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[2]))
                    ->setCity($sAddress->extractAddressPart('city', $addressMatch[3]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[3]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}