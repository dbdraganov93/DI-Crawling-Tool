<?php

/**
 * Store Crawler für Mrs Sporty (ID: 68940)
 */
class Crawler_Company_MrsSporty_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.mrssporty.de';
        $searchUrl = $baseUrl . '/finde-deinen-club/?location=[zip]&lng=[lng]&lat=[lat]';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sRegion = new Marktjagd_Database_Service_GeoRegion();        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $regions = $sRegion->findZipCodesByNetSize(15, true);
        
        $count = count($regions);
        $i = 1;
        foreach ($regions as $zipcode){
            $url = preg_replace(array('#\[zip\]#', '#\[lng\]#', '#\[lat\]#'),
                            array($zipcode['zip'], $zipcode['lng'], $zipcode['lat']),
                            $searchUrl);            
            
            $this->_logger->info("crawl $url\n $i of $count");
            
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();
            
            $qStores = new Zend_Dom_Query($page, 'UTF-8');
            $nStores = $qStores->query("div[class*=\"details-2\"]");

            foreach ($nStores as $nStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $details = $nStore->c14n();

                $details = utf8_decode($details);
                
                $details = str_replace("\xc2\x9f", '', $details);
                $details = str_replace('', 'ß', $details);
                $details = str_replace('Ã¼', 'ü', $details);
                $details = str_replace('', 'ö', $details);
                $details = str_replace('Ã¤', 'ä', $details);
                $details = str_replace('', 'Ü', $details);
                $details = str_replace('Ã¶', 'Ö', $details);
                $details = str_replace('', 'Ä', $details);                                
                
                if (preg_match('#<div[^>]*class="name"[^>]*>([^<]+)</div>#', $details, $nameMatch)){
                    $eStore->setText('Kontakt: ' . trim($nameMatch[1]));
                }
                                
                if (preg_match('#<div[^>]*class="address"[^>]*>(.+?)</div>#is', $details, $addressMatch)){
                    $addressLines = preg_split('#<br[^>]*>#', $addressMatch[1]);
                    
                    if (!preg_match('#deutsch#i', $addressLines[2])){
                        $this->_logger->info("no german location, skip: $addressMatch[1]");
                        continue;
                    }
                    
                    $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                            ->setCity($sAddress->extractAddressPart('city', $addressLines[1]));
                }
                
                if (preg_match('#<div[^>]*class="tel"[^>]*>(.+?)</div>#', $details, $phoneMatch)){
                    $eStore->setPhone($sAddress->normalizePhoneNumber(preg_replace('#<[^>]*>#', '', $phoneMatch[1])));
                }
                
                if (substr($eStore->getPhone, 0, 1) != 0){
                    $eStore->setPhone('0' . $eStore->getPhone);
                }
                
                if (preg_match('#<div[^>]*class="email"[^>]*>(.+?)</div>#', $details, $mailMatch)){
                    $eStore->setEmail(trim(preg_replace('#<[^>]*>#', '', $mailMatch[1])));
                }
                
                if (preg_match('#<div[^>]*class="opening-hours"[^>]*>(.+)$#', $details, $hoursMatch)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }
                
                $cStores->addElement($eStore);                                
            }
            $i++;
        }        
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
