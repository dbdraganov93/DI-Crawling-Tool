<?php

/**
 * Store Crawler für Bäckerei Middelberg (ID: 71415)
 */
class Crawler_Company_Middelberg_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.baeckerei-middelberg.de/';
        $searchUrl = $baseUrl . 'map/node/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<section[^>]*id=".+?shop-list-block"[^>]*>(.+?)</section#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="\/node\/([0-9]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStoreNumber) {
            $sPage->open($baseUrl . 'node/' . $singleStoreNumber);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<article[^>]*>(.+?)</article>#s';
            if (!preg_match($pattern, $page, $storeDetailMatch)) {
                $this->_logger->err($companyId . ': unable to get store details for: ' . $singleStoreNumber);
                continue;
            }
            
            $pattern = '#<div[^>]*>\s*<p[^>]*>(.+?)</p#';
            if (!preg_match($pattern, $storeDetailMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address for: ' . $singleStoreNumber);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
            
            $pattern = '#Telefon:?(.+?)</p#';
            if (preg_match($pattern, $storeDetailMatch[1], $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#Öffnungszeiten:?(.+?Uhr.+?)</p#';
            if (preg_match($pattern, $storeDetailMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $eStore->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress)-1]))
                    ->setZipCode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress)-1]))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress)-2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress)-2])))
                    ->setStoreNumber($singleStoreNumber);
            
            if (count($aAddress) == 4) {
                $eStore->setText($aAddress[1]);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
