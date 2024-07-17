<?php

/**
 * Store Crawler für Becker + Flöge (ID: 68887)
 */
class Crawler_Company_BeckerUndFloege_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.becker-floege.de/';
        $searchUrl = $baseUrl . 'mein-geschaeft/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="marker-item(.+?)</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#data-marker-id="([^"]+?)"#';
            if (!preg_match($pattern, $singleStore, $storeNumberMatch)) {
                throw new Exception($companyId . ': unable to get store id.');
            }
            
            $pattern = '#property="streetAddress"[^>]*>(.+?)<#';
            if (!preg_match($pattern, $singleStore, $streetMatch)) {
                throw new Exception($companyId . ': unable to get store street.');
            }
            
            $pattern = '#property="addressLocality"[^>]*>(.+?)<#';
            if (!preg_match($pattern, $singleStore, $cityMatch)) {
                throw new Exception($companyId . ': unable to get store locality.');
            }
            
            $pattern = '#property="latitude"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $latMatch)) {
                $eStore->setLatitude($latMatch[1]);
            }
            
            $pattern = '#property="longitude"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $lngMatch)) {
                $eStore->setLongitude($lngMatch[1]);
            }
            
            $pattern = '#property="description"[^>]*>.+?<b>Geschäftsleiter</b>\s*<br[^>]*>(.+?)<br[^>]*>(.+?)</i>#s';
            if (preg_match($pattern, $singleStore, $textMatch)) {
                $eStore->setText('Geschäftsleitung: ' . $textMatch[1] . ' - ' . $textMatch[2]);
            }
            
            $pattern = '#Telefon:\s*(.+?)<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#Telefax:\s*(.+?)<#';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
              
            $pattern = '#Öffnungszeiten:?\s*(.+?)</p#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                if (preg_match ('#(Adventssamstage.+?$)#', $storeHoursMatch[1], $notesMatch)){
                    $eStore->setStoreHoursNotes($notesMatch[1]);
                    $storeHoursMatch[1] = preg_replace('#Adventssamstage.+?$#', '', $storeHoursMatch[1]);
                }                
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $eStore->setStoreNumber($storeNumberMatch[1])
                    ->setStreet($sAddress->extractAddressPart('street', $streetMatch[1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $streetMatch[1]))
                    ->setCity($sAddress->extractAddressPart('city', $cityMatch[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $cityMatch[1]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}