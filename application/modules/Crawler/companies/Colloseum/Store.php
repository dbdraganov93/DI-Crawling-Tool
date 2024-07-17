<?php

/* 
 * Store Crawler für Colloseum (ID: 29117)
 */

class Crawler_Company_Colloseum_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.mycolloseum.com';
        $overViewUrl = $baseUrl . '/stores?field_store_brand_tid=All&country=All&field_city_tid=All';
                
        $distributionMap = array(
            "colloseum"     => "Colloseum", 
            "forever"   => "Forever 18", 
            "fashionclub"    => "Fashion Club"
        );
                
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        if(!$sPage->open($overViewUrl)){
            throw new Exception('Could not open url:' . $overViewUrl);
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        if (!preg_match('#jQuery\.extend\(Drupal\.settings\,\s*(\{\"basePath.+?)\)\;#', $page, $jsonMatch)) {
            throw new Exception('unable to get stores for company with id ' . $companyId);
        }
        
        $json = json_decode($jsonMatch[1]);        
        
        foreach ($json->gmap->auto1map->markers as $marker){
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setDistribution($distributionMap[$marker->markername])
                    ->setLatitude((string) $marker->latitude)
                    ->setLongitude((string) $marker->longitude)
                    ->setWebsite($baseUrl . $marker->link);
            
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
                        
            if (preg_match('#<div[^>]*class="[^"]*views-field-title[^"]*"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setSubtitle(trim(strip_tags($match[1])));
            }
            
            if (preg_match('#<div[^>]*class="[^"]*views-field-street[^"]*"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setStreetAndStreetNumber(trim(strip_tags($match[1])));
            }
            
            if (preg_match('#<div[^>]*class="[^"]*views-field-city[^"]*"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setCity(trim(strip_tags($match[1])));
            }
            
            if (preg_match('#<div[^>]*class="[^"]*views-field-phone[^"]*"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setPhoneNormalized(trim(strip_tags($match[1])));
            }
            
            if (preg_match('#"mailto:([^"]+)"#', $page, $match)){
                $eStore->setEmail(trim(strip_tags($match[1])));
            }
            
            if (preg_match('#<div[^>]*class="[^"]*field-name-field-opening-hours[^"]*"[^>]*>(.+?)</div>#', $page, $match)){                
                $eStore->setStoreHoursNormalized($match[1]);
            }

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }

        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}