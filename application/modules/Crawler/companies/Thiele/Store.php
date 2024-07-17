<?php

/*
 * Store Crawler für Feinbäckerei Thiele (ID: 71598)
 */

class Crawler_Company_Thiele_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.thiele.info/';
        $searchUrl = $baseUrl . 'filialen-n/';
        $detailUrl = $baseUrl . 'front_content.php?idart=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="javascript:mapfx\((\d{3}),#';
        if (!preg_match_all($pattern, $page, $storeCityMatches)) {
            throw new Exception($companyId . ': unable to get any store city ids.');
        }

        foreach ($storeCityMatches[1] as $singleStoreCity) {
            $sPage->open($detailUrl . $singleStoreCity);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#href="javascript:mapfx2\((\d{3}),#';
            if (!preg_match_all($pattern, $page, $storeIdMatches)) {
                $this->_logger->info($companyId . ': only one store for city: ' . $singleStoreCity);
                $aStoreIds[] = $singleStoreCity;
                continue;
            }
            
            $aStoreIds = array_merge($aStoreIds, $storeIdMatches[1]);
        }
        $aStoreIds = array_unique($aStoreIds);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreIds as $singleStoreId) {
            $sPage->open($detailUrl . $singleStoreId);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)\s*<br[^>]*>\s*(<strong[^>]*>[^<]+?</strong>\s*<br[^>]*>)?\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreId);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#fnungszeiten(.+?)(</p>|<br[^>]*>\s*<br[^>]*>)#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Tel([^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[3]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
