<?php

/* 
 * Store Crawler für Planet Sport (ID: 71069)
 */

class Crawler_Company_PlanetSports_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.planet-sports.de';
        $searchUrl = $baseUrl . '/stores/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a\s*href="(https://www.planet-sports.de/stores/[^"]+)"\s*#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }        
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $eStore = new Marktjagd_Entity_Api_Store();
         
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore->setWebsite($singleStoreUrl);
                        
            if (preg_match('#<h1>(.+?)</h1>#', $page, $match)){
                $eStore->setSubtitle($match[1]);
            }                        
            
            if (preg_match('#<div>.*?Adresse.*?</div>\s*<div>.+?</div>\s*<div>(.+?)</div>#is', $page, $match)){
                $match[1] = str_replace('P7, 16-18', 'P7 16-18', $match[1]);                
                $addressLines = explode(',', $match[1]);               
                
                $eStore->setStreetAndStreetNumber($addressLines[0])
                        ->setZipcodeAndCity($addressLines[1]);
            }
            
            if (preg_match('#"mailto:([^"]+)"#', $page, $match)){
                $eStore->setEmail($match[1]);
            }
            
            if (preg_match('#>Telefon:</[^>]*>(.+?)<#is', $page, $match)){             
                $eStore->setPhoneNormalized($match[1]);
            }
            
            if (preg_match('#<div>.*?Öffnungszeiten.*?</div>\s*<div>(.+?)</div>#is', $page, $match)){
                $eStore->setStoreHoursNormalized($match[1]);
            }
            
            if (preg_match('#var\s*lat\s*=\s*(.+?);#is', $page, $match)){             
                $eStore->setLatitude($match[1]);
            }
            
            if (preg_match('#var\s*lng\s*=\s*(.+?);#is', $page, $match)){             
                $eStore->setLongitude($match[1]);
            }
                                    
            if (preg_match('#<ul[^>]*class="store-gallery-bxslider"[^>]*>(.+?)<ul>#', $page, $match)){
                $images = array();
                if (preg_match_all('#<img[^>]*src="//([^"]+)"#', $match[1], $submatch)){
                    foreach ($submatch[1] as $imageUrl){
                        $sDownload = new Marktjagd_Service_Transfer_Download();
                        $sHttp = new Marktjagd_Service_Transfer_Http();
                        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
                        $downloadPathFile = $sDownload->downloadByUrl(
                            'http:' . $imageUrl,
                            $downloadPath);
                        
                    rename($downloadPathFile, $downloadPathFile . '.jpg');
                    
                    $images[] = $sHttp->generatePublicHttpUrl($downloadPathFile . '.jpg');
                    }
                }
            }
            
            
            
            $eStore->setImage(implode(',', array_slice($images, 0, 5)));
            
            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}