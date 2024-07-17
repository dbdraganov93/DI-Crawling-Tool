<?php

/**
 * Store Crawler für Jibi (ID: 28976)
 */
class Crawler_Company_Jibi_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.jibi.de';
        $searchUrl = $baseUrl . '/maerkte/marktsuche-ajax/?'
                . 'tx_sharpnessmarkt_sharpnessmarktmap[action]=marketdata&'
                . 'tx_sharpnessmarkt_sharpnessmarktmap[controller]=Femarktmap';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $strService = '';
            $pattern = '#([0-9]+)$#';
            if (!preg_match($pattern, $singleJStore->link, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number.');
                continue;
            }
            
            $pattern = '#([^<]+?)\s*<.+href="tel:([^"]+?)"#';
            if (!preg_match($pattern, $singleJStore->city, $cityPhoneMatch)) {
                $this->_logger->err($companyId . ': unable to get store city.');
                continue;
            }
            
            $pattern = '#ffnungszeiten(.+?)</strong#';
            if (preg_match($pattern, $singleJStore->openings, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setStreetAndStreetNumber($singleJStore->street)
                    ->setCity($cityPhoneMatch[1])
                    ->setZipcode($singleJStore->zip)
                    ->setImage($baseUrl . '/uploads/tx_sharpnessmarkt/maerkte/' . $singleJStore->image)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lon)
                    ->setStoreNumber($storeNumberMatch[1])
                    ->setWebsite($baseUrl . '/maerkte/oeffnungszeiten/markt-im-detail/?tx_sharpnessmarkt_sharpnessmarktdetail[uid]=' . $eStore->getStoreNumber())
                    ->setPhoneNormalized($cityPhoneMatch[2]);

            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#class="services"[^>]*>(.+?)<div\s*class="clear#s';
            if (preg_match($pattern, $page, $serviceMatch)) {
                $pattern = '#class="name"[^>]*>\s*(.+?)\s*<#s';
                if (preg_match_all($pattern, $serviceMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#zahlung#i', $singleService)) {
                            $eStore->setPayment(preg_replace('#\s*-\s*#', '', trim(strip_tags($singleService))));
                            continue;
                        }
                        if (strlen($strService)) {
                            $strService .= ',';
                        }
                        $strService .= preg_replace('#\s*-\s*#', '', trim(strip_tags($singleService)));
                    }
                }
            }
            
            $pattern = '#allgemeiner\s*service.+?<ul[^>]*>(.+?)</ul#si';
            if (preg_match($pattern, $page, $serviceMatch)) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li#s';
                if (preg_match_all($pattern, $serviceMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (strlen($strService)) {
                            $strService .= ',';
                        }
                        $strService .= preg_replace('#\s*-\s*#', '', trim(strip_tags($singleService)));
                    }
                }
            }
            
            $pattern = '#mit\s*behinderung.+?<ul[^>]*>(.+?)</ul#si';
            if (preg_match($pattern, $page, $serviceMatch)) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li#s';
                if (preg_match_all($pattern, $serviceMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#park#', $singleService)) {
                            $eStore->setBarrierFree(1);
                            continue;
                        }
                        if (strlen($strService)) {
                            $strService .= ',';
                        }
                        $strService .= preg_replace('#\s*-\s*#', '', trim(strip_tags($singleService)));
                    }
                }
            }
            
            $eStore->setService($strService);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}