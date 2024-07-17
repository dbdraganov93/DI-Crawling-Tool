<?php

/**
 * Store Crawler für Santander Bank (ID: 71663)
 */
class Crawler_Company_Santander_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.santanderbank.de/';
        $searchUrl = $baseUrl . 'csdlv/ContentServer?pagename=BuscadorOficinas/Page/'
                . 'BOF_SearchInBounds&eid=1278678753991&leng=de_DE&lat='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&range='
                . '100&frame=1&format=XML';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $xmlStores = new SimpleXMLElement($page, LIBXML_NOCDATA);
            if (!property_exists($xmlStores, 'row')) {
                continue;
            }
            
            foreach ($xmlStores->row as $singleXmlStore) {
                if (preg_match('#atm#', $singleXmlStore->name)) {
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#>\s*([^>]+?)\s*</p#';
                if (!preg_match_all($pattern, $singleXmlStore->ampliado, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleXmlStore->id);
                }
                
                $pattern = '#ffnungszeiten(.+?)</table#s';
                if (preg_match($pattern, $singleXmlStore->ampliado, $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#-#', '#bis#'), array(',', '-'), $storeHoursMatch[1])));
                }
                
                $eStore->setCity(utf8_decode($sAddress->extractAddressPart('city', $addressMatch[1][1])))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[1][1]))
                        ->setStreet(preg_replace(array('#[\x00-\x1F\x80-\xBF]#u', '#tr.+?e#'), array('', 'traße'), $sAddress->normalizeStreet($sAddress->extractAddressPart('street', utf8_decode($addressMatch[1][0])))))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1][0])))
                        ->setPhone($sAddress->normalizePhoneNumber($addressMatch[1][2]))
                        ->setFax($sAddress->normalizePhoneNumber($addressMatch[1][3]))
                        ->setStoreNumber($eStore->getHash());
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}