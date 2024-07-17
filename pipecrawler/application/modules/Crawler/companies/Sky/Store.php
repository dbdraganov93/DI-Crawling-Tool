<?php
/* 
 * Store Crawler für Sky (ID: 28685)
 */
class Crawler_Company_Sky_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'http://www.sky.coop';
        $searchUrl = $baseUrl . '/maerkte-oeffnungszeiten';                
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();       

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        if (!preg_match_all('#<a[^>]*href="(http://www.sky.coop/sky-supermarkt[^"]+)"#', $page, $linkMatch)) {
            $this->_logger->err('Company ID: ' . $companyId . ': unable to find any store links');
        }                        
        
        $linkMatch[1] = array_unique($linkMatch[1]);
        
        foreach ($linkMatch[1] as $storeLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $sPage->open($storeLink);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore->setWebsite($storeLink);
                        
            if (preg_match('#<div[^>]*itemprop="name"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setSubtitle(strip_tags($match[1]));
            }
            
            if (preg_match('#<div[^>]*itemprop="streetAddress"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setStreetAndStreetNumber(strip_tags($match[1]));
            }
            
            if (preg_match('#<span[^>]*itemprop="postalCode"[^>]*>(.+?)</span>#', $page, $match)){
                $eStore->setZipcode(strip_tags($match[1]));
            }
            
            if (preg_match('#<span[^>]*itemprop="addressLocality"[^>]*>(.+?)</span>#', $page, $match)){
                $eStore->setCity(strip_tags($match[1]));
            }
            
            if (preg_match('#<span[^>]*itemprop="telephone"[^>]*>(.+?)</span>#', $page, $match)){
                $eStore->setPhoneNormalized(strip_tags($match[1]));
            }
                        
            if (preg_match('#<ul[^>]*class="table"[^>]*>\s*<li[^>]*class="time"[^>]*>(.+?)</ul>#', $page, $match)){
                $eStore->setStoreHoursNormalized($match[1]);
            }
            
            if (preg_match('#<ul[^>]*class="services"[^>]*>(.+?)</ul>#', $page, $match)){
                if (preg_match_all('#<span[^>]*class="label"[^>]*>(.+?)</span>#', $match[1], $submatch)){
                    $eStore->setService(implode(', ', $submatch[1]));
                }
            }

            if (preg_match('#google\.maps\.LatLng\(([^\,]+)\,([^\)]+)\)#', $page, $match)){
                $eStore->setLatitude($match[1])
                        ->setLongitude($match[2]);
            }
            
            
            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
