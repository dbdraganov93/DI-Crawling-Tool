<?php

/**
 * Store Crawler für Spiel und Spass (ID: 69200)
 */
class Crawler_Company_SpielUndSpass_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.spielundspass.de';
        
        $searchUrls = array(
            $baseUrl . '/spiel-spass/haendleruebersicht/00000-19999.html',
            $baseUrl . '/spiel-spass/haendleruebersicht/20000-39999.html',
            $baseUrl . '/spiel-spass/haendleruebersicht/40000-59999.html',
            $baseUrl . '/spiel-spass/haendleruebersicht/60000-79999.html',
            $baseUrl . '/spiel-spass/haendleruebersicht/80000-99999.html',
            );

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();                
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($searchUrls as $singleUrl) {            
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            if (!preg_match_all('#<div[^>]*class="large-6 columns"[^>]*>\s*(<h2>.+?)</div>#', $page, $match)){
                throw new Exception('company: ' . $companyId . 'canot find stores on: ' . $singleUrl);
            }

            foreach ($match[1] as $storeText){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $storeText = preg_replace('#<h2>SPIEL & SPASS</h2>#', '', $storeText);
                if (preg_match('#<h2>([^<]+)</h2><p>([^<]+)<br />([^<]+).*?</p>#', $storeText, $addressMatch)){
                    $eStore->setSubtitle(trim($addressMatch[1]))
                            ->setStreet($sAddress->extractAddressPart('street', $addressMatch[2]))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressMatch[2]))
                            ->setCity($sAddress->extractAddressPart('city', $addressMatch[3]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[3]));
                } 

                if (preg_match('#<h3>Öffnungszeiten</h3>(.+?)$#', $storeText, $hoursMatch)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }
       
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}