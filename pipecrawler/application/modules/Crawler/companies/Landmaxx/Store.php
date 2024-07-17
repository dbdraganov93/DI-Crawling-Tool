<?php

/* 
 * Store Crawler für Landmaxx (ID: 68969)
 */

class Crawler_Company_Landmaxx_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://landmaxx.de/';
        $searchUrl = $baseUrl . 'popups/standorte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div\s*id="landcardentry_[0-9]+"[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([^<]+?)<#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any infos: ' . $singleStore);
                continue;
            }
            
            $strTime = '';
            for ($i = 0; $i < count($infoMatches[1]); $i++) {
                if (preg_match('#^[0-9]{5}\s+#', $infoMatches[1][$i])) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $infoMatches[1][$i]))
                            ->setCity($sAddress->extractAddressPart('city', $infoMatches[1][$i]))
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $infoMatches[1][$i - 1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $infoMatches[1][$i - 1])));
                    continue;
                }
                
                if (preg_match('#Tel#', $infoMatches[1][$i])) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($infoMatches[1][$i]));
                    continue;
                }
                
                if (preg_match('#Fax#', $infoMatches[1][$i])) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($infoMatches[1][$i]));
                    continue;
                }
                
                if (preg_match('#Uhr#', $infoMatches[1][$i])) {
                    if (strlen($strTime)) {
                        $strTime .= ', ';
                    }
                    $strTime .= $infoMatches[1][$i - 1] . ' ' . $infoMatches[1][$i];
                    continue;
                }
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($strTime))
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}