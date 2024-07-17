<?php

/* 
 * Store Crawler für Autoland Deutschland (ID: 72100)
 */

class Crawler_Company_Autoland_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://autoland.de/';
        $searchUrl = $baseUrl . 'auto-kaufen-niederlassungen';

        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match_all('#<a\s*href="(' . $baseUrl . 'autoland-niederlassungen/[^"]+)"#', $page, $matchStoreList)) {
            throw new Exception('couldn\'t find any store url on page ' . $searchUrl);
        }

        foreach ($matchStoreList[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="fil\_addr[^"]*"[^>]*>.+?<br[^>]*>\s*(.+?)\s*<br[^>]*>\s*(.+?)\s*</div>#';
            if (!preg_match($pattern, $page, $matchAddress)) {
                $this->_logger->err('unable to match address for url ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($matchAddress[1]);
            $eStore->setZipcodeAndCity($matchAddress[2]);

            $pattern = '#<div[^>]*class="opening"[^>]*>\s*(.+?)\s*</div>#';
            if (preg_match($pattern, $page, $matchOpening)) {
                $eStore->setStoreHoursNormalized($matchOpening[1]);
            }

            $pattern = '#<div[^>]*class="standort\_foto"[^>]*>\s*<a[^>]*href="(.+?)"#';
            if (preg_match($pattern, $page, $matchImg)) {
                $eStore->setImage($matchImg[1]);
            }

            $pattern = '#<div[^>]*class="standort\_daten"[^>]*>\s*Niederlassungsleiter\s*'
                . '<br[^>]*>\s*(.+?)\s*'
                . '<br[^>]*>\s*(.+?)\s*'
                . '<br[^>]*>\s*(.+?)\s*<br[^>]*>#';

            if (preg_match($pattern, $page, $matchContact)) {
                $eStore->setText('Niederlassungsleiter ' . $matchContact[1]);
                $eStore->setPhoneNormalized(str_replace('Tel: ', '', strip_tags($matchContact[2])));
                $eStore->setFaxNormalized(str_replace('Fax: ', '', strip_tags($matchContact[3])));
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}