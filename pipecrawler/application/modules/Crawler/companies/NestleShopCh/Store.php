<?php

/* 
 * Store Crawler für Nestlé Shop CH (ID: 72201)
 */

class Crawler_Company_NestleShopCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.nestle-shop.ch/';
        $searchUrl = $baseUrl . 'de/storelocator/';
        
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        $result = curl_exec($ch);
        
        $page = preg_replace('#\s+#', ' ', $result);
        
        $pattern = '#<div[^>]*class="store-locator"[^>]*>.+?<tbody[^>]*>(.+?)</table#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<tr[^>]*>(.+?)</tr#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>([^<]+?)<[^>]*>\s*(\d{4}\s+[^<]+?)<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $pattern = '#itemprop="(streetAddress|postalCode|addressLocality)"[^>]*>(\s*[^<]+?<[^>]*>\s*)?([^<]+?)</span#';
                if (!preg_match_all($pattern, $singleStore, $addressMatches)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                
                $aAddress = array_combine($addressMatches[1], $addressMatches[3]);
                
                $addressMatch[1] = $aAddress['streetAddress'];
                $addressMatch[2] = $aAddress['postalCode'] . ' ' . $aAddress['addressLocality'];
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<td[^>]*>\s*(Mo.+?)</td#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#(\d)h(\d)#', '$1:$2', $storeHoursMatch[1]));
            }
            
            $pattern = '#daddr=([^,]+?),([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'CH');
            
            if (preg_match('#4612#', $eStore->getZipcode())) {
                $eStore->setStreet(preg_replace('#Fabrik\s*Wangen\s+#', '', $eStore->getStreet()));
            }

            $cStores->addElement($eStore);
        }
        
        return $this->getResponse($cStores, $companyId);
    }
}