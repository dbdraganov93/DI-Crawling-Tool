<?php
/**
 * Storecrawler für Zumnorde (ID: 70956)
 */

class Crawler_Company_Zumnorde_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        
        $baseUrl    =   'http://www.zumnorde.de/';
        $searchUrl  =   'schuhhausfinder/';
        $stores     =   'schuhhaeuser/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        if (!$sPage->open($baseUrl . $searchUrl)) {
            throw new Exception($companyId . ': unable to open baseUrl: ' . $baseUrl . $searchUrl);
        }

        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#https://www.zumnorde.de/schuhhaeuser(.+?)\">#';
        if (!preg_match_all($pattern, $page, $aStoreLinks)) {
            throw new Exception($companyId . ': unable to get store list from url: ' . $baseUrl . $searchUrl);
        }
        
        $storeList = array_unique($aStoreLinks[1]);

        foreach ($storeList as $storeLink) {
            
            $eStore = new Marktjagd_Entity_Api_Store();
            if (!$sPage->open($baseUrl . $stores . $storeLink)) {
                $this->_logger->warn($companyId . ': unable to get store detail page from url: ' . $storeLink);
                continue;
            }
            $page = $sPage->getPage()->getResponseBody();
            $eStore->setWebsite($baseUrl . $stores . $storeLink);
            
            $pattern = '#itemprop\s*=\s*\"streetAddress\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $address)) {
                $this->_logger->warn($companyId . ': unable to get store address from url: ' . $storeLink);
                continue;
            }            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $address[1])));
            $eStore->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnummber', $address[1])));
            
            $pattern = '#itemprop\s*=\s*\"postalCode\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $postalCode)) {
                $this->_logger->warn($companyId . ': unable to get store postal code from url: ' . $storeLink);
                continue;
            }
            $eStore->setZipcode(trim($postalCode[1]));
            
            $pattern = '#itemprop\s*=\s*\"addressLocality\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $city)) {
                $this->_logger->warn($companyId . ': unable to get store city from url: ' . $storeLink);
                continue;
            }
            $eStore->setCity($sAddress->normalizeCity($sAddress->extractAddressPart('city', $city[1])));
            
            $pattern = '#itemprop\s*=\s*\"name\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $name)) {
                $this->_logger->info($companyId . ': unable to get store name from url: ' . $storeLink);
            }
            $eStore->setTitle($name[1]);
            
            $pattern = '#itemprop\s*=\s*\"telephone\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $tel)) {
                $this->_logger->info($companyId . ': unable to get store telephone from url: ' . $storeLink);
            }
            $eStore->setPhone($sAddress->normalizePhoneNumber($tel[1]));
            
            $pattern = '#itemprop\s*=\s*\"faxNumber\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $fax)) {
                $this->_logger->info($companyId . ': unable to get store fax from url: ' . $storeLink);
            }
            $eStore->setFax($sAddress->normalizePhoneNumber($fax[1]));
            
            $pattern = '#itemprop\s*=\s*\"email\">([^<]+?)<#';
            if (!preg_match($pattern, $page, $email)) {
                $this->_logger->info($companyId . ': unable to get store email from url: ' . $storeLink);
            }
            $eStore->setEmail($sAddress->normalizeEmail($email[1]));
            
            $pattern = '#itemprop\s*=\s*\"openingHours\">(.+?)<\/span#';
            if (!preg_match($pattern, $page, $openingHours)) {
                $this->_logger->info($companyId . ': unable to get store opening hours from url: ' . $storeLink);
            }
            $eStore->setStoreHours($sTimes->generateMjOpenings($openingHours[1]));
            
            $pattern = '#<div\s*class=\"item\".+?data-src\s*=\s*\"(.+?)\"#';
            if (!preg_match($pattern, $page, $imgUrl)) {
                $this->_logger->info($companyId . ': unable to get store image from url: ' . $storeLink);
            }
            $eStore->setImage('https:' . $imgUrl[1]);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}


