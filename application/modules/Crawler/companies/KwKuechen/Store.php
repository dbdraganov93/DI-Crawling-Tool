<?php

/*
 * Store Crawler für kw Küchen (ID: 71875)
 */

class Crawler_Company_KwKuechen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.kuechenwerkstatt.de/';
        $searchUrl = $baseUrl . 'kontakt/kontakt.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeo->findZipCodesByNetSize();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $sPage->open($searchUrl, array('query' => $singleZipcode));
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="ahb-search__results"[^>]*>(.+?)</div#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no stores for zipcode: ' . $singleZipcode);
                continue;
            }

            $pattern = '#<a[^>]*>(.+?)</a#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores from list: ' . $singleZipcode);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#<h2[^>]*>\s*küchenwerkstatt[^<]+?<#i';
                if (!preg_match($pattern, $singleStore)) {
                    $this->_logger->info($companyId . ': not a kuechenwerkstatt. skipping...');
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#>\s*([^·<]+?)\s*\·\s*([^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                
                $eStore->setAddress($addressMatch[1], $addressMatch[2]);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
