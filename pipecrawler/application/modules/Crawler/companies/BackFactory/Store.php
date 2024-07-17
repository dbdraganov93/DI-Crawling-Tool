<?php

/* 
 * Store Crawler für Back-Factory (ID: 29062)
 */

class Crawler_Company_BackFactory_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $searchUrl = 'https://www.back-factory.de/index.php?eID=tx_locator_eID';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $zipcodes = $sGeo->findZipCodesByNetSize(18);
        $postParams = array();
        $postParams['data'] = urldecode('%7B%22includeLibs%22%3A%22typo3conf%5'
                . 'C%2Fext%5C%2Flocator%5C%2Fpi1%5C%2Fclass.tx_locator_pi1.php'
                . '%22%2C%22userFunc%22%3A%22tx_locator_pi1-%3Emain%22%2C%22_L'
                . 'OCAL_LANG.%22%3A%7B%22default.%22%3A%7B%22noResults%22%3A%2'
                . '2No+results%21%22%7D%2C%22de.%22%3A%7B%22noResults%22%3A%22'
                . 'Keine+Treffer%21%22%7D%2C%22en.%22%3A%7B%22noResults%22%3A%'
                . '22No+results%21%22%7D%2C%22pl.%22%3A%7B%22noResults%22%3A%2'
                . '2nie+znaleziono%21%22%7D%7D%2C%22pid_list%22%3A%2263%22%2C%'
                . '22displayMode%22%3A%22ajaxSearchWithMap%22%2C%22showAreas%2'
                . '2%3A%22%22%2C%22showPoiTable%22%3A%220%22%2C%22defaultMapTy'
                . 'pe%22%3A%22%22%2C%22defaultZoomLevel%22%3A%22%22%2C%22enabl'
                . 'eStreetViewOverlay%22%3A%220%22%2C%22enableStreetView%22%3A'
                . '0%2C%22enableMoreButton%22%3A%220%22%2C%22additionalLayers%'
                . '22%3A%22%22%2C%22apiV3Layers%22%3A%22%22%2C%22storeTitle%22'
                . '%3A%22store%28s%29%22%2C%22linkTarget%22%3A%22_blank%22%2C%'
                . '22routeViewTarget%22%3A%22_top%22%2C%22templateFile%22%3A%2'
                . '2fileadmin%5C%2Flayout%5C%2Fdefault%5C%2Fextensions%5C%2Flo'
                . 'cator%5C%2Ftemplate.html%22%2C%22cssFile%22%3A%22EXT%3Aloca'
                . 'tor%5C%2Fpi1%5C%2Flayout.css%22%2C%22finderTemplate%22%3A%2'
                . '21%22%2C%22uidOfSingleView%22%3A%2263%22%2C%22useFeUserPage'
                . 'Title%22%3A%220%22%2C%22showOverviewMap%22%3A%220%22%2C%22u'
                . 'seFeUserData%22%3A%220%22%2C%22externalLocationTable%22%3A%'
                . '22%22%2C%22feUserGroup%22%3A%22%22%2C%22tt_addressStorename'
                . 'Field%22%3A%22%22%2C%22tt_addressAllowedGroups%22%3A%22%22%'
                . '2C%22tt_addressNotAllowedGroups%22%3A%22%22%2C%22showTabbed'
                . 'InfoWindow%22%3A%221%22%2C%22showVideoPlayer%22%3A%220%22%2'
                . 'C%22numberOfMails%22%3A%22%22%2C%22showOnlyMailResultTable%'
                . '22%3A%220%22%2C%22shapeFile%22%3A%22fileadmin%5C%2Fincludes'
                . '%5C%2Flocator%5C%2Fshape.shp%22%2C%22fillZipcodeArea%22%3A%'
                . '221%22%2C%22zoomZipcodeArea%22%3A%22%22%2C%22additionalMapT'
                . 'ypes%22%3A%22%22%2C%22helpPageId%22%3A%22%22%2C%22useNotesI'
                . 'nternal%22%3A%220%22%2C%22enableLogs%22%3A%220%22%2C%22debu'
                . 'g%22%3A%220%22%2C%22radiusPresets%22%3A%2220+km%2C50+km%2C1'
                . '00+km%22%2C%22resultLimit%22%3A%22100%22%2C%22googleApiKey%'
                . '22%3A%22%22%2C%22googleApiVersion%22%3A%223.x%22%2C%22useCu'
                . 'rl%22%3A%22%22%2C%22countryCodes%22%3A%22de%22%2C%22searchB'
                . 'yName%22%3A0%2C%22resultPageId%22%3A%2263%22%2C%22countrySe'
                . 'lectorResultPageId%22%3A%22%22%2C%22routePageId%22%3A%2263%'
                . '22%2C%22feUserListingPageId%22%3A%22%22%2C%22showStoreImage'
                . '%22%3A%221%22%2C%22distanceUnit%22%3A%22km%22%2C%22useCooki'
                . 'e%22%3A0%2C%22cookieLifeTime%22%3A%223600%22%2C%22showResul'
                . 'tTable%22%3A0%2C%22showRoute%22%3A0%2C%22_GP%22%3A%7B%22mod'
                . 'e%22%3A%22ajaxSearchWithMap%22%2C%22lat%22%3A%22%22%2C%22lo'
                . 'n%22%3A%22%22%7D%2C%22lang%22%3A%22de%22%2C%22pageId%22%3A%'
                . '2263%22%7D');
        
        $page = $sPage->getPage();
        $page->setMethod('POST');
        $page->setUseCookies(true);
        $sPage->setPage($page);
        
        foreach ($zipcodes as $singleZip) {
            $postParams['ref'] = $singleZip . ':DE:20 km:';
            $postParams['tx_locator_pi1[action]'] = 'getMarkers';
            $postParams['tx_locator_pi1[pw]'] = '0';
            
            $postParams['tx_locator_pi1[search]'] = $singleZip . ':DE:20 km:';
           
            $sPage->open($searchUrl, $postParams);
            $page = $sPage->getPage()->getResponseBody();

            // Find all store infos
            $pattern = '#<div class="storename">(.+?)</div></div></div>#';
            if(!preg_match_all($pattern, $page, $storeInfo)){
                continue;
            }
            
            foreach ($storeInfo[1] as $singleStoreInfo){
                $eStore = new Marktjagd_Entity_Api_Store();

                // Set store name
                $pattern ='#<b>(.+?)</b>#';
                if(preg_match($pattern, $singleStoreInfo, $storeName)){
                    $eStore->setTitle($storeName[1]);
                }
                
                // Set store address
                $pattern ='#<div class="address">(.+?)</div>#';
                if(preg_match($pattern, $singleStoreInfo, $storeAddress)){
                    $eStore->setStreet($sAddress->
                        extractAddressPart('street', $storeAddress[1]));
                    $eStore->setStreetNumber($sAddress->
                        extractAddressPart('street_number', $storeAddress[1]));
                }
                
                // Set store city and zipcode
                $pattern='#<div class="city">(.+?)</div>#';
                if(preg_match($pattern, $singleStoreInfo, $storeCity)){
                    $eStore->setZipcode($sAddress
                            ->extractAddressPart('zipcode', $storeCity[1]));
                    $eStore->setCity($sAddress
                            ->extractAddressPart('city', $storeCity[1]));
                }
                
                $pattern='#<div class="phone">(.*?)</div>#';
                if(preg_match($pattern, $singleStoreInfo, $storePhone)){                    
                   $eStore->setPhone($sAddress
                           ->normalizePhoneNumber($storePhone[1]));
                }                
                
                $pattern='#<div class="fax">(.*?)</div>#';
                if(preg_match($pattern, $singleStoreInfo, $storeFax)){
                    
                   $eStore->setFax($sAddress
                           ->normalizePhoneNumber($storeFax[1]));
                }
                
                $pattern='#<div class="hours">(.+?)$#';
                if(preg_match($pattern, $singleStoreInfo, $storeHours)){
                    $eStore->setStoreHours($sTimes
                            ->generateMjOpenings($storeHours[1]));
                }
                
                $services = '';
                $pattern = '#Icons-Catering#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $services .= ' Catering,';
                }
                
                $patter = '#Icons-Lieferservice#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $services .= ' Lieferservice,';
                }
                
                $pattern = '#Icons-Sitzbereich-innen#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $services .= ' Sitzbereich-innen,';
                }
                
                $pattern = '#Icons-Sitzbereich-aussen#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $services .= ' Sitzbereich-außen,';
                }
                
                $pattern = '#Icons-Vorbestellung#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $services .= ' Vorbestellung,';
                }
                
                preg_match('#^\s(.+?),$#', $services, $servicesClean);
                $eStore->setService($servicesClean[1]);
                
                $pattern = '#Icons-Kinderwagen#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $eStore->setBarrierFree('Ja');
                }
                
                $pattern = '#Icons-Rollstuhl#';
                if(preg_match($pattern, $singleStoreInfo)) {
                    $eStore->setBarrierFree('Ja');
                }
                
                $cStores->addElement($eStore);
            } 
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }   
}
