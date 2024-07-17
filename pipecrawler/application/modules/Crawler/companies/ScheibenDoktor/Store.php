<?php

/*
 * Store Crawler für Scheiben Doktor (ID: 28942)
 */

class Crawler_Company_ScheibenDoktor_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.scheiben-doktor.de';
        $searchUrl = $baseUrl . '/filialfinder/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
       
        $aParams = array(
            'tx_krbustorefinder_main[__referrer][@extension]' => 'KrbuStorefinder',
            'tx_krbustorefinder_main[__referrer][@vendor]' => 'Kreativburschen',
            'tx_krbustorefinder_main[__referrer][@controller]' => 'Store',
            'tx_krbustorefinder_main[__referrer][@action]' => 'list',
            'tx_krbustorefinder_main[__referrer][arguments]' => 'YToxOntzOjEwOiJjb250cm9sbGVyIjtzOjA6IiI7fQ==10a8273a53adbae0593ff397ae896695bcb5646e',
            'tx_krbustorefinder_main[__trustedProperties]' => 'a:1:{s:6:"filter";a:7:{s:3:"zip";i:1;s:4:"city";i:1;s:9:"perimeter";i:1;s:10:"typeNormal";i:1;s:18:"typeServiceStation";i:1;s:18:"typeServicePartner";i:1;s:10:"typeAgency";i:1;}}8226dfc7f77bd862b82b295d0ed05d077531764b',
            'tx_krbustorefinder_main[filter][zip]' => '99099',
            'tx_krbustorefinder_main[filter][city]' => '',
            'tx_krbustorefinder_main[filter][perimeter]' => '1000',
            'tx_krbustorefinder_main[filter][typeNormal]' => '1',
            'tx_krbustorefinder_main[filter][typeServiceStation]' => '',
            'tx_krbustorefinder_main[filter][typeServicePartner]' => '',
            'tx_krbustorefinder_main[filter][typeAgency]' => ''
        );

        $sPage->open($searchUrl, $aParams);
        $page = $sPage->getPage()->getResponseBody();

        if (preg_match_all('#(<li[^>]*class="[^"]*store[^"]*"[^>]+>.+?</li>)#is', $page, $storeMatches)){
            foreach ($storeMatches[1] as $storeMatch){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                if (preg_match('#data-lat="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setLatitude(trim($match[1]));
                }
                
                if (preg_match('#data-lon="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setLongitude(trim($match[1]));
                }
                
                if (preg_match('#data-name="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setSubtitle(trim($match[1]));
                }
                
                if (preg_match('#data-uid="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setStoreNumber(trim($match[1]));
                }
                
                if (preg_match('#data-street="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setStreet($sAddress->extractAddressPart('street', $match[1]))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $match[1]));
                }
                
                if (preg_match('#data-zip="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setZipcode(trim($match[1]));
                }
                
                if (preg_match('#data-city="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setCity(trim($match[1]));
                }
                
                if (preg_match('#<dt>Telefon</dt>\s*<dd>(.+?)</dd>#', $storeMatch, $match)){
                    $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
                }
                
                if (preg_match('#<dt>Fax</dt>\s*<dd>(.+?)</dd>#', $storeMatch, $match)){
                    $eStore->setFax($sAddress->normalizePhoneNumber($match[1]));
                }
                
                if (preg_match('#mailto:([^"]+)"#', $storeMatch, $match)){
                    $eStore->setEmail($match[1]);
                }
                
                if (preg_match('#href="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setWebsite($match[1]);
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
