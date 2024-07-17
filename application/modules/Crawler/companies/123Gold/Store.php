<?php

/* 
 * Store Crawler für 123Gold (ID: 69151)
 */

class Crawler_Company_123Gold_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.123gold.de/';
        $searchUrl = $baseUrl . 'standorte_de.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a\s*href="([^"]+)"\s*itemprop="url"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $storeDetailUrl = $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*id="panelContactInformation"[^>]*>(.+?)</div>\s*</div>#s';
            if (!preg_match($pattern, $page, $storeInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }
            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*(.+?)\s*</span#';
            if (!preg_match_all($pattern, $storeInfoListMatch[1], $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $aInfos = array_combine($storeInfoMatches[1], $storeInfoMatches[2]);
            
            $pattern = '#<img[^>]src="([^"]+?location_([0-9]+?)_[^"]+?\.jpg)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setStoreNumber($imageMatch[2]);
            }
            
            $pattern = '#google\.maps\.LatLng\((.+?),\s*(.+?)\)#';
            if (preg_match($pattern, $page, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }
            
            $eStore->setSubtitle($aInfos['owns'])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', strip_tags($aInfos['address']))))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', strip_tags($aInfos['address'])))
                    ->setZipcode($aInfos['postalCode'])
                    ->setCity($aInfos['addressLocality'])
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos['telephone']))
                    ->setFax($sAddress->normalizePhoneNumber($aInfos['faxnumber']))
                    ->setEmail(preg_replace('#^(.+?)<.+#', '$1', $aInfos['email']))
                    ->setStoreHours($sTimes->generateMjOpenings($aInfos['openingHours']))
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}