<?php

/*
 * Store Crawler für MPreis AT (ID: 72285)
 */

class Crawler_Company_MPreisAt_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mpreis.at/';
        $searchUrl = $baseUrl . 'standorte/maerktefilialen/index.htm';
        $sPage = new Marktjagd_Service_Input_Page();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $localDistPath = APPLICATION_PATH . '/../public/files/dataAt/' . $companyId . '/mpreis_dists.csv';
        $aData = $sExcel->readFile($localDistPath, TRUE, ';')->getElement(0)->getData();
        
        $aStoresForDist = array();
        foreach ($aData as $singleLine) {
            $aStoresForDist[$singleLine['store_number']] = preg_replace('#K.+?rnten#', 'Kärnten', $singleLine['distribution']);
        }
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select[^>]*name="tx_usrmbshops_pi1\[showUid\]"[^>]*>(.+?)</select#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<option[^>]*value="(\d+)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeIdMatches)) {
            throw new Exception($companyId . ': unable to get store ids from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeIdMatches[1] as $singleStoreId) {
            $storeDetailUrl = $searchUrl . '?tx_usrmbshops_pi1%5BshowUid%5D=' . $singleStoreId;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*adresse[^>]*>\s*<img[^>]*>\s*</div>\s*<p[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*,\s*#', $addressMatch[1]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Aus\s*Österreich:\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#<div[^>]*oeffnungszeiten[^>]*>\s*<img[^>]*>\s*</div>\s*<p[^>]*>\s*(.+?)\s*<(b|/p)>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setZipcode(end($aAddress))
                    ->setStreetAndStreetNumber($aAddress[1])
                    ->setStoreNumber($singleStoreId)
                    ->setWebsite($storeDetailUrl)
                    ->setDistribution($aStoresForDist[$singleStoreId]);
            
            $pattern = '#<area[^>]*onmouseover="setzenamen\(\'([^\']+?)\'\)"[^>]*href="https://www.mpreis.at/standorte/maerktefilialen/index.htm\?no_cache=1&tx_usrmbshops_pi1%5BshowPostcodeShops%5D=' . $eStore->getZipcode() . '#';
            if (!preg_match($pattern, $page, $cityMatch)) {
                $this->_logger->err($companyId . ': unable to get store city: ' . $eStore->getZipcode());
                continue;
            }
            
            $eStore->setCity($cityMatch[1]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
