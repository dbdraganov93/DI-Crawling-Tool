<?php

/**
 * Store Crawler für WMF (ID: 353)
 */
class Crawler_Company_Wmf_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.wmf.de/';
        $searchUrl = $baseUrl . 'de_de/wmf-erleben/metanavi/handel-filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $aParams = array(
            'country' => 'DE',
            'store_locator[filialen]' => '2',
            'store_locator[find]' => 'Finden',
            'store_locator[query]' => '79588',
            'store_locator[radius]' => '2000'
        );
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $sPage->open($searchUrl, $aParams);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<span[^>]*class="num"[^>]*>(.+?)</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*class="opening"[^>]*>(.+?)</div#';
            if (preg_match($pattern, $singleStore, $storesHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storesHoursMatch[1]));
            }
            
            $pattern = '#<p[^>]*class="storedata"[^>]*>\s*(.+?)\s*</p#';
            if (!preg_match($pattern, $singleStore, $storeDetailMatch)) {
                $this->_logger->err($companyId . ': unable to get store details: ' . $singleStore);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatch[1]);
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[2]))
                    ->setFax($sAddress->normalizePhoneNumber($aAddress[3]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
