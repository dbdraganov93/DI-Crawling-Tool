<?php

/**
 * Store Crawler für National Bank (ID: 71661)
 */
class Crawler_Company_NationalBank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.national-bank.de/';
        $searchUrl = $baseUrl . 'service-center/standorte-und-oeffnungszeiten/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#div[^>]*id="location(.+?)</div#is';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $pattern = '#span\s*class="bold"[^>]*>\s*(.+?)\s*</p#s';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
            
            $pattern = '#fon(.+?)<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#fax(.+?)<#i';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#mailto:([^>]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#zeiten(.+)#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[1])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[2]))
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
