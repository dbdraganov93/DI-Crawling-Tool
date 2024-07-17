<?php

/*
 * Store Crawler für Lada (ID: 71784)
 */

class Crawler_Company_Lada_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.lada4you.de/';

        $params = 'ref=01309&tx_locator_pi1[action]=getMarkers&tx_locator_pi1[search]=01309:DE:1000:&tx_locator_pi1[pw]=0'
         . '&data=%7B%22includeLibs%22%3A%22typo3conf%5C%2Fext%5C%2Flocator%5C%2Fpi1%5C%2Fclass.tx_locator_pi1.php%22%2C%22userFunc%22%3A%22tx_locator_pi1-%3Emain%22%2C%22pid_list%22%3A%224%22%2C%22displayMode%22%3A%22ajaxSearchWithMap%22%2C%22showAreas%22%3A%22%22%2C%22showPoiTable%22%3A%221%22%2C%22defaultMapType%22%3A%22%22%2C%22defaultZoomLevel%22%3A%22%22%2C%22enableStreetViewOverlay%22%3A%220%22%2C%22enableStreetView%22%3A0%2C%22enableMoreButton%22%3A%221%22%2C%22additionalLayers%22%3A%22%22%2C%22apiV3Layers%22%3A%22%22%2C%22storeTitle%22%3A%22store%28s%29%22%2C%22linkTarget%22%3A%22_blank%22%2C%22routeViewTarget%22%3A%22_blank%22%2C%22templateFile%22%3A%22EXT%3Alocator%5C%2Fpi1%5C%2Ftemplate.html%22%2C%22cssFile%22%3A%22EXT%3Alocator%5C%2Fpi1%5C%2Flayout.css%22%2C%22finderTemplate%22%3A%220%22%2C%22uidOfSingleView%22%3A%221%22%2C%22useFeUserPageTitle%22%3A%221%22%2C%22showOverviewMap%22%3A%221%22%2C%22useFeUserData%22%3A%220%22%2C%22externalLocationTable%22%3A%22tx_cal_location%22%2C%22feUserGroup%22%3A%222%22%2C%22tt_addressStorenameField%22%3A%22name%22%2C%22tt_addressAllowedGroups%22%3A%22%22%2C%22tt_addressNotAllowedGroups%22%3A%22%22%2C%22showTabbedInfoWindow%22%3A%221%22%2C%22showVideoPlayer%22%3A%221%22%2C%22numberOfMails%22%3A%223%22%2C%22showOnlyMailResultTable%22%3A%220%22%2C%22shapeFile%22%3A%22fileadmin%5C%2Fincludes%5C%2Flocator%5C%2Fshape.shp%22%2C%22fillZipcodeArea%22%3A%221%22%2C%22zoomZipcodeArea%22%3A%225%22%2C%22additionalMapTypes%22%3A%22G_PHYSICAL_MAP%2CG_HYBRID_MAP%22%2C%22helpPageId%22%3A%22%22%2C%22useNotesInternal%22%3A%220%22%2C%22enableLogs%22%3A%220%22%2C%22debug%22%3A%220%22%2C%22radiusPresets%22%3A%2250%2C100%2C500%2C1000%22%2C%22resultLimit%22%3A%22300%22%2C%22googleApiKey%22%3A%22%22%2C%22googleApiVersion%22%3A%223.1%22%2C%22useCurl%22%3A%22%22%2C%22countryCodes%22%3A%22de%22%2C%22searchByName%22%3A0%2C%22resultPageId%22%3A%22%22%2C%22countrySelectorResultPageId%22%3A%22%22%2C%22routePageId%22%3A%22%22%2C%22feUserListingPageId%22%3A%22%22%2C%22showStoreImage%22%3A%221%22%2C%22distanceUnit%22%3A%22km%22%2C%22useCookie%22%3A0%2C%22cookieLifeTime%22%3A%223600%22%2C%22showResultTable%22%3A0%2C%22showRoute%22%3A0%2C%22_GP%22%3A%7B%22mode%22%3A%22ajaxSearchWithMap%22%2C%22lat%22%3A%22%22%2C%22lon%22%3A%22%22%7D%2C%22lang%22%3A%22de%22%2C%22pageId%22%3A%2296%22%7D';

        $searchUrl = $baseUrl . 'index.php?eID=tx_locator_eID' . '&' . $params;
        $sPage = new Marktjagd_Service_Input_Page();;

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $cStores = new Marktjagd_Collection_Api_Store();

        $pattern = '#<div\s*class="storename"[^>]*>(.*?)<div\s*class="url"[^>]*>#is';

        if (!preg_match_all($pattern, $page, $matchAllStores)) {
            throw new Exception('no stores available for lada store crawler');
        }

        foreach ($matchAllStores[1] as $storeString) {

            $eStore = new Marktjagd_Entity_Api_Store();

            $patternTitle = '#<strong>(.*?)</strong>#is';
            if (preg_match($patternTitle, $storeString, $matchTitle)) {
                $eStore->setTitle($matchTitle[1]);
            }

            $patternAddress = '#<div\s*class="address"\s*>\s*(.*?)\s*</div>#is';
            if (preg_match($patternAddress, $storeString, $matchAddress)) {
                $eStore->setStreetAndStreetNumber($matchAddress[1]);
            }

            $patternCity = '#<div\s*class="city"\s*>\s*(.*?)\s*</div>#is';
            if (preg_match($patternCity, $storeString, $matchCity)) {
                $eStore->setZipcodeAndCity($matchCity[1]);
            }

            $patternPhone = '#<div\s*class="phone"\s*>\s*(.*?)\s*</div>#is';
            if (preg_match($patternPhone, $storeString, $matchPhone)) {
                $eStore->setPhoneNormalized($matchPhone[1]);
            }

            $patternMail = '#<a\s*href="mailto:(.*?)"#is';
            if (preg_match($patternMail, $storeString, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
