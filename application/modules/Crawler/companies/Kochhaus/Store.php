<?php

/* 
 * Store Crawler für Kochhaus (ID: 71166)
 */

class Crawler_Company_Kochhaus_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.kochhaus.de/';
        $searchUrl = $baseUrl . 'maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*name="maerkte"[^>]*>(.+?)<footer#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list: ' . $searchUrl);
        }
        
        $pattern = '#href="(https:\/\/www\.kochhaus\.de[^"]+?)"[^>]*>\s*<div#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls from list: ' . $searchUrl);
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $storeDetailUrl) {
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#Anschrift(.+?)</tr#';
            if (!preg_match($pattern, $page, $addressFieldMatch)) {
                $this->_logger->err($companyId . ': unable to get store address field: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)*(\([^\)]+?\))*(\s*<[^>]*>\s*)*(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $addressFieldMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from field: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</tr#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<img[^>]*src="([^"]+?)"[^>]*class="categoryPicture"[^>]*>#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($imageMatch[1]);
            }
            
            $pattern = '#google\.maps\.LatLng\(([^,]+?)\s*,\s*([^\(]+?)\)#';
            if (preg_match($pattern, $page, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[5]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}