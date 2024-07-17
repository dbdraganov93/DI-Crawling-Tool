<?php

/**
 * Store Crawler für UnityMedia (ID: 70822)
 */

class Crawler_Company_Unitymedia_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://app.unitymedia.de';
        $searchUrl = $baseUrl . '/shopsuche.html/shops/search?utf8=%E2%9C%93&layout=shoplocator&search=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 25);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*id="Store_content"[^>]*>(.+?)</div>\s*<div[^>]*id="Fachhaendler_content"#is';
            if (!preg_match($pattern, $page, $storeContent)) {
                $this->_logger->warn('cannot match store div: ' . $singleUrl);
                continue;
            }
                                   
            $pattern = '#<div[^>]*class="[^"]*shop[^"]*"[^>]*>(.+?)\s*<div[^>]*class="opening_hours"[^>]*>(.+?)</div>\s*<div[^>]*class="info"[^>]*>(.+?)</div>#is';
            if (!preg_match_all($pattern, $storeContent[1], $shopItems)){
                $this->_logger->err('cannot match store items: ' . $singleUrl);
                continue;
            }           
            
            foreach ($shopItems[0] as $itemIdx => $shopItem){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                if (preg_match('#<h4>(.+?)</h4>#', $shopItems[1][$itemIdx], $titleMatch)){
                    $eStore->setSubtitle(trim($titleMatch[1]));
                }
                
                if (preg_match('#<img[^>]*src="([^"]+)"#', $shopItems[1][$itemIdx], $imageMatch)){                    
                    $eStore->setLogo($baseUrl . trim($imageMatch[1]));
                }
                
                if (preg_match('#<strong[^>]*class="street"[^>]*>(.+?)</strong>#', $shopItems[2][$itemIdx], $addressMatch)){
                    $addressLine = explode(',', $addressMatch[1]);
                    $eStore->setStreet($sAddress->extractAddressPart('street', trim($addressLine[0])))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', trim($addressLine[0])))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', trim($addressLine[1])))
                            ->setCity($sAddress->extractAddressPart('city', trim($addressLine[1])));                    
                }
                
                if (preg_match('#<p>(.+?)</p>#', $shopItems[2][$itemIdx], $hoursMatch)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }

                if ($eStore->getZipcode() == 1){
                    $eStore->setZipcode('68161')
                            ->setCity('Mannheim');                         
                }
                
                if ($eStore->getZipcode() == '44263'){
                    $eStore->setEmail('hoerde@unitymediashops.de')
                            ->setWebsite('www.unitymediashops.de')
                            ->setStoreHours('Mo-Do 09:30-18:00,Fr 09:30-19:00,Sa 09:30-16:00')
                            ->setStoreHoursNotes('An Sonntagsspieltagen des BVB (Heimspiel)von 10:00 Uhr bis eine Stunde nach Spielende');
                }
                
                
                if ($eStore->getZipcode() == '44866'){
                    $eStore->setEmail('wattenscheid@unitymediashops.de')
                            ->setWebsite('www.unitymediashops.de')
                            ->setPhone('021193674150')
                            ->setStoreHours('Mo-Fr 09:30-18:30,Sa 09:30-14:00');                            
                }
                
                if ($eStore->getZipcode() == '58452'){
                    $eStore->setEmail('witten@unitymediashops.de')
                            ->setWebsite('www.unitymediashops.de')
                            ->setPhone('021193674150')
                            ->setStoreHours('Mo-Fr 09:30-18:30,Sa 09:30-15:00');                            
                }
                
                if ($eStore->getZipcode() == '72070'){
                    $eStore->setEmail('kornhausstr@ich-will-unitymedia.de')
                            ->setWebsite('www.ich-will-unitymedia.de')
                            ->setPhone('070715668000');                            
                }
                
                if ($eStore->getZipcode() == '72074'){
                    $eStore->setEmail('muehlstr@ich-will-unitymedia.de')
                            ->setWebsite('www.ich-will-unitymedia.de')
                            ->setPhone('07071856586');                            
                }
                
                if ($eStore->getZipcode() == '42103') {
                    $eStore->setStoreHours('Mo-Fr 10:00-19:0,Sa 10:00-16:00');
                }
                
                if ($eStore->getZipcode() == '42275'){
                    $eStore->setEmail('barmen@unitymediashops.de')
                            ->setWebsite('www.unitymediashops.de')
                            ->setPhone('021193674150')
                            ->setStoreHours('Mo-Fr 10:00-19:00,Sa 10:00-16:00');                            
                }
                
                if ($eStore->getZipcode() == '44575'){
                    $eStore->setEmail('castrop-rauxel@unitymediashops.de')
                            ->setWebsite('www.unitymediashops.de')
                            ->setPhone('021193674150')
                            ->setStoreHours('Mo-Fr 10:00-18:00,Sa 10:00-14:00');                            
                }
                
                $cStores->addElement($eStore, true);
            }            
        }                
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}