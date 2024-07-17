<?php

/**
 * Store Crawler für Arko (ID: 29005)
 */
class Crawler_Company_Arko_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.arko.de/';
        $searchUrl = $baseUrl . 'filialfinder/index/search/?lat=50.9847679&lng=11.0298799&address=%25%25&radius=1000';
        
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
                     
        $sPage->open($searchUrl);        
        $xml = simplexml_load_string($sPage->getPage()->getResponseBody());        
        
        $count = 1;
        foreach ($xml as $singleXmlStore){
            $this->_logger->info('open store ' . $count++ . ' of ' . count($xml));
            $eStore = new Marktjagd_Entity_Api_Store();
                        
            $eStore->setStoreNumber((string) $singleXmlStore->attributes()->location_id)
                    ->setLatitude((float) $singleXmlStore->attributes()->latitude)
                    ->setLongitude((float) $singleXmlStore->attributes()->longitude);
            
            $opening = preg_replace(array('#(bis|&ndash;)#','#\.#','#&nbsp;#'), array('-',':',' '), (string) $singleXmlStore->attributes()->opening);
			$eStore->setStoreHours($sTimes->generateMjOpenings($opening));

            if(!preg_match('#([^"]+)<br[^>]*>([0-9]{5}[^"]+)#', $singleXmlStore->attributes()->address_display, $addrMatch)) {
            	continue; // Österreich und Tschechien haben keine 5 stellige PLZ
            }
                        
            preg_match('#([^<]*)<br />#', $addrMatch[1], $subTitleMatch);            
            $street = preg_replace('#[^<]*<br />#', '', $addrMatch[1]);
            $eStore->setSubtitle($subTitleMatch[1])
            		->setStreet($sAddress->extractAddressPart('street', $street))
            		->setStreetNumber($sAddress->extractAddressPart('street_number', $street))
            		->setCity($sAddress->extractAddressPart('city', preg_replace('#(<br[^>]*/>[^<]*|\&\#40\;[^\#]*\&\#41\;)#', '',  $addrMatch[2])))
            		->setZipcode($sAddress->extractAddressPart('zipcode', $addrMatch[2]));        
            
            $eStore->setPhone($sAddress->normalizePhoneNumber(html_entity_decode((string)$singleXmlStore->attributes()->phone)));            

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }       
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
