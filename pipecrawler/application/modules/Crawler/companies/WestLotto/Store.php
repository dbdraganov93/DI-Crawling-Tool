<?php

/*
 * Store Crawler für WestLotto (ID: 71772)
 */

class Crawler_Company_WestLotto_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.westlotto.com/';
        $searchUrl = $baseUrl . 'wlinfo/WL_InfoService';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDb->findAllZipCodes();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'gruppe' => 'AstSuche',
            'client' => 'wlincl',
            'size' => '100',
            'ort' => '',
            'x' => '0',
            'y' => '0');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['plz'] = $singleZipcode ;

            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#es\s*wurden\s*[0-9]+\s*annahmestellen\s*gefunden\:(.+?)</body#is';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no stores available for zipcode: ' . $singleZipcode);
                continue;
            }

            $pattern = '#<td>\s*(.+?)\s*<br>.+?Karte\s*</span>(.+?)</table#s';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from list: ' . $singleZipcode);
                continue;
            }

            for ($i = 0; $i < count($storeMatches[1]); $i++) {
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setSubtitle(trim($storeMatches[1][$i]));
                
                $pattern = '#>\s*([^<]+?)\s*<br[^>]*>\s*([0-9]{5}[^<]+?)\s*<#';
                if (!preg_match($pattern, $storeMatches[2][$i], $storeAddressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                
                $pattern = '#<td\s*class="tdrightpadding"[^>]*>(.+)#';
                if (preg_match($pattern, $storeMatches[2][$i], $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                }
                
                $pattern = '#>\s*([^<]+?)\s*<br[^>]*>\s*([^0-9]{5}[^<]+?)\s*<#';
                if (preg_match($pattern, $storeMatches[2][$i], $storeChefMatch)) {
                    $eStore->setText('Filialleiter: ' . $storeChefMatch[1] . ' ' . $storeChefMatch[2]);
                }
                
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeAddressMatch[1])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddressMatch[1])))
                        ->setCity($sAddress->extractAddressPart('city', $storeAddressMatch[2]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $storeAddressMatch[2]));
                
                $cStores->addElement($eStore, true);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
