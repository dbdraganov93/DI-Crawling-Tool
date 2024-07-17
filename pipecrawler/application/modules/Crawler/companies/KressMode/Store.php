<?php

/*
 * Store Crawler für Kress Mode (ID: 67693)
 */

class Crawler_Company_KressMode_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.kress-mode.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*kress-store-listitem"(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $pattern = '#data-store-[^=]+?="([^"]+?)"#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStore);
                continue;
            }
            
            $aAddress = preg_split('#\s+-\s+#', $infoMatches[1][3]);
            
            $pattern = '#Öffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#öffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $singleStore, $storeHoursNotesMatch)) {
                $eStore->setStoreHoursNotes('Sonderöffnungszeiten: ' . trim(strip_tags($storeHoursNotesMatch[1])));
            }
            
            $pattern = '#Abteilungen(.+)</p#';
            if (preg_match($pattern, $singleStore, $sectionListMatch)) {
                $pattern = '#<span[^>]*>\s*(.+?)\s*</span#';
                if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                    $eStore->setSection(implode(', ', $sectionMatches[1]));
                }
            }
            
            $pattern = '#a[^>]*href="(http:\/\/www\.kress-mode\.de\/filialen\/[^"]+?)"#';
            if (preg_match($pattern, $singleStore, $storeUrlMatch)) {
                $eStore->setWebsite($storeUrlMatch[1]);
                
                $sPage->open($storeUrlMatch[1]);
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#Tel(.+?)<#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
            }
            
            $eStore->setLatitude($infoMatches[1][1])
                    ->setLongitude($infoMatches[1][2])
                    ->setZipcode($infoMatches[1][4])
                    ->setCity($infoMatches[1][5])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', end($aAddress))))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', end($aAddress))));
            
            if (preg_match('#Gewerbegebiet\s*|Gewerbegebiet\s*Ost\s*#', end($aAddress))) {
                $eStore->setStreet(preg_replace('#(Gewerbegebiet\s*|Gewerbegebiet\s*Ost\s*)#', '', $sAddress->normalizeStreet($sAddress->extractAddressPart('street', end($aAddress)))));
            }
            
            $eStore->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
