<?php

/**
 * Store Crawler für Frisör Klier (ID: 22385)
 */
class Crawler_Company_Klier_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.klier.de/';
        $searchUrl = $baseUrl . 'salons/umkreis/geo/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $aLinks = $sGen->generateUrl($searchUrl, Marktjagd_Service_Generator_Url::$_TYPE_COORDS, 0.1);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aLinks as $singleLink) {
            if (!$sPage->open($singleLink)) {
                $this->_logger->err($companyId . ': unable to open store list page. url: ' . $singleLink);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();
            $pattern = '#href="(https://www.klier.de/salons/details/[^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores. url: ' . $singleLink);
                continue;
            }

            foreach ($storeLinkMatches[1] as $singleStoreLink) {
                $eStore = new Marktjagd_Entity_Api_Store();
                if (!$sPage->open($singleStoreLink)) {
                    $this->_logger->err($companyId . ': unable to open store detail page. url: ' . $singleStoreLink);
                    continue;
                }
                
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#<div[^>]*class="salons-two-column"[^>]*>(.+?)</div#s';
                if (!preg_match_all($pattern, $page, $storeDetailMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store details. url: ' . $singleStoreLink);
                    continue;
                }
                
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatches[1][0]);
                $aTimes = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatches[1][1]);
                
                $eStore->setSubtitle(trim(strip_tags($aAddress[0])))
                        ->setStreetAndStreetNumber($aAddress[1])
                        ->setZipcodeAndCity($aAddress[2])
                        ->setPhoneNormalized($aAddress[3])
                        ->setStoreHoursNormalized($aTimes[1]);
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
