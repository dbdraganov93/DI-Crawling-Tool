<?php

/**
 * Store Crawler für Holzland (ID: 68896)
 */
class Crawler_Company_Holzland_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://haendler.holzland.de';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#traderJSON\s*=\s*(.+?);#';
        if (!preg_match($pattern, $page, $storesMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $jStores = json_decode($storesMatch[1]);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (strlen($singleJStore->plz) != 5
                    || preg_match('#(\.it|\.fr)$#', $singleJStore->email)) {
                continue;
            }
            $strSection = '';
            if ($singleJStore->hqBodenVerf == '1') {
                $strSection = 'HQ Boden verfügbar';
            }
            
            if ($singleJStore->hqGartenWeltVerf == '1') {
                if (strlen($strSection)) {
                    $strSection .= ', ';
                }
                $strSection .= 'HQ Garten verfügbar';
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleJStore->zrNummer)
                    ->setTitle($singleJStore->name)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->adresse)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->adresse)))
                    ->setZipcode($singleJStore->plz)
                    ->setCity($singleJStore->ort)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->telefon))
                    ->setWebsite($singleJStore->weblink)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setEmail($singleJStore->email)
                    ->setSection($strSection);
            
            if (strlen($singleJStore->bild)) {
                $eStore->setLogo($baseUrl . $singleJStore->bild);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}