<?php

/**
 * Storecrawler für We Fashion (ID: 69947)
 */
class Crawler_Company_WeFashion_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.wefashion.de/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-WE-DE-Site/de_DE/Stores-FindByCriteria';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();

        $aParams = array(
            'dwfrm_storelocator_country' => 'DE',
            'dwfrm_storelocator_isshowresults' => 'true',
            'dwfrm_storelocator_latitude' => '',
            'dwfrm_storelocator_longitude' => '',
            'search-by-location' => 'false'
        );

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aZipcodes = $sDbGeoRegion->findZipCodesByNetSize(25);

        $aStoreNumbers = array();
        foreach ($aZipcodes as $singleZip) {
            $aParams['dwfrm_storelocator_town'] = $singleZip;
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*id="map\-data-results"[^>]*>\s*(.*?)\s*</div>#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': unable to get store-list for zipcode: ' . $singleZip);
                continue;
            }

            $jStores = json_decode($storeListMatch[1]);
            if (count($jStores)) {
                foreach ($jStores as $singleJStore) {
                    if (!in_array($singleJStore->storeId, $aStoreNumbers)) {
                        $aStoreNumbers[] = $singleJStore->storeId;
                    }
                }
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreNumbers as $singleStoreNumber) {
            $storeDetailUrl = $baseUrl . 'on/demandware.store/Sites-WE-DE-Site/de_DE/'
                    . 'Stores-Details?StoreID=' . $singleStoreNumber . '&format=ajax';
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="store-details-address"[^>]*>(.+?)\s*</div>\s*</div>#';
            if (!preg_match($pattern, $page, $addressListMatch)) {
                $this->_logger->err($companyId . ': unable to get store address list: '. $singleStoreNumber);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}\s+[A-ZÄÖÜ].+)#';
            if (!preg_match($pattern, $addressListMatch[1], $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address parts from list: '. $singleStoreNumber);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<li[^>]*class="store-detail-line"[^>]*>\s*(\d+\s*-\s*\d+)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</div>\s*</div>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<img[^>]*src="\/([^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            if (!preg_match('#DE\/#', $eStore->getImage())) {
                continue;
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[3]);
            
            $aAddress = preg_split('#\s+#', $eStore->getCity());
            $eStore->setCity(end($aAddress));
            
            if (count($aAddress) > 1) {
                $strSubtitle = '';
                for ($i = 0; $i < count($aAddress) - 1; $i++) {
                    if (strlen($strSubtitle)) {
                        $strSubtitle .= ' ';
                    }
                    $strSubtitle .= $aAddress[$i];
                }
                $eStore->setSubtitle($strSubtitle);
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
