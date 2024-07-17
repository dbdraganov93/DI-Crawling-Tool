<?php

/* 
 * Store Crawler für Kuechen Aktuell (ID: 71170)
 */

class Crawler_Company_KuechenAktuell_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        
        $sPage      = new Marktjagd_Service_Input_Page();
        $sAddress   = new Marktjagd_Service_Text_Address();
        $sTimes     = new Marktjagd_Service_Text_Times();
        $cStores    = new Marktjagd_Collection_Api_Store();
        $sCsv       = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);

        // Open location overview page
        $baseUrl    = 'http://www.kuechen-aktuell.de';
        $searchUrl  = '/standorte/kuechenstudio.html';
        $sPage->open($baseUrl . $searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Grep location list
        $pattern = '#<div id="so-list">(.+?)<\/div>#';
        if (!preg_match($pattern, $page, $locList)) {
            throw new Exception($companyId . ': unable to match location list.');
        }

        // Grep location urls out of locList
        $pattern = '#<li id=".+?href=".(.+?)">+?#';
        if (!preg_match_all($pattern, $locList[1], $locUrls)) {
            throw new Exception($companyId . ': unable to match location urls.');
        }
        
        foreach ($locUrls[1] as $singleUrl) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $sPage->open($baseUrl . $singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            // Greb and set store subtitle
            $pattern = '#<div[^>]*id="coNavH1">.+?<h1[^>]*>(.+?)<\/h1>#';
            if (!preg_match($pattern, $page, $subtitle)) {
                $this->_logger->warn($companyId . ': unable to match subtitle.');
            } else {            
                $tmp = preg_replace('#^.+?<br[^>]*\/>in\s#', '', $subtitle[1]);
                if (strpos($tmp, 'Küchen') === false) {
                    $eStore->setSubtitle($tmp);
                } else {
                    $this->_logger->info($companyId . ': Invalid subtitle.');
                }
            }
            
            // Grep store information
            // $locInfos[1][0] = address and contact data
            // $locInfos[1][1] = store hours
            $pattern = '#class="data">(.+?)<\/div>#';
            if (!preg_match_all($pattern, $page, $storeInfos)) {
                $this->_logger->warn($companyId . ': unable to match store infos.');
                continue;
            }
            
            // Grep and set store address
            $pattern = '#<p[^>]*>.+?<br[^>]*>(.+?)<br[^>]*>(.+?)<\/p>#';
            if (!preg_match_all($pattern, $storeInfos[1][0], $storeAddress)) {
                $this->_logger->warn($companyId . ': unable to match store address.');
                continue;
            } else {
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeAddress[1][0])));
                $eStore->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddress[1][0])));
                $eStore->setCity($sAddress->extractAddressPart('city', $storeAddress[2][0]));
                $eStore->setZipcode($sAddress->extractAddressPart('zip', $storeAddress[2][0]));
            }

            // Grep and set store contact data
            $pattern = '#class="right">.+?<li>(.+?)<\/li>.+?<li>(.+?)<\/li>.+?<li>(.+?)<\/li>#';
            if (!preg_match($pattern, $storeInfos[1][0], $contact)){
                $this->_logger->warn($companyId . ': unable to match contact data.');
            } else {
                $eStore->setPhone($sAddress->normalizePhoneNumber($contact[1]));
                $eStore->setFax($sAddress->normalizePhoneNumber($contact[2]));
                $eStore->setEmail($sAddress->normalizeEmail($contact[3]));
            }

            // Grep and set store hours
            $pattern = '#<li>(.+?)<\/li>#';
            if (!preg_match_all($pattern, $storeInfos[1][1], $storeHours)){
                $this->_logger->warn($companyId . ': unable to match store hours.');
            } else {
                $tmp = '';

                if ((count($storeHours[1]) % 2) == 0) {
                    $c = count($storeHours[1]) / 2;
                } else {
                    $c = (count($storeHours[1]) - 1) / 2;
                    $this->_logger->warn($companyId . ': possible problem with store hours'
                            . ', because the array size is odd.');
                }

                for ($i = 0; $i < $c; $i++) {
                    $tmp .= $storeHours[1][$i] . ' ' . $storeHours[1][$i+2] . ' ';
                }

                $eStore->setStoreHours($sTimes->generateMjOpenings($tmp));
            }

            // Grep store services
            $pattern = '#<div id="cont5".+?<ul>(.+?)<\/ul>#';
            if (!preg_match($pattern, $page, $storeServices)){
                $this->_logger->warn($companyId . ': unable to match store services.');
            } else {
                $tmp = preg_replace('#<li>#', '', $storeServices[1]);
                $tmp = preg_replace('#<\/li>#', ', ', $tmp);
                $eStore->setService($tmp);
            }
            
            // Greb and set geo coordinates            
            $pattern = '#\"fldStandort"[^>]*value=\"(.+?)\"[^>]*>#';
            if(!preg_match($pattern, $page, $fldStandort)){
                $this->_logger->warn($companyId . ': unable to match store fldStandort id.');
            } else {
                $sPage->open('http://www.kuechen-aktuell.de/fileadmin/maps/maps_standorte.php?standort=' . $fldStandort[1]);
                $geoPage = $sPage->getPage()->getResponseBody();
                
                preg_match('#google.maps.LatLng\((.+?)\),#', $geoPage, $latLng);
                $tmp = preg_split('#,#', $latLng[1]);
                $eStore->setLatitude(trim($tmp[0]));
                $eStore->setLongitude(trim($tmp[1]));
            } 
            
            $cStores->addElement($eStore);
        }

        $fileName   = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
