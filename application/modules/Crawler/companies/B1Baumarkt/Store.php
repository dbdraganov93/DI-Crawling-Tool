<?php

/**
 * Store Crawler für B1 Discount Baumarkt (ID: 22382)
 */
class Crawler_Company_B1Baumarkt_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        
        $baseUrl    = 'http://b1-discount.de/';
        $searchUrl  = 'index.php?option=com_storelocator&view=map&format=raw&searchall=1&Itemid=102&catid=-1&tagid=-1&featstate=0';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($baseUrl . $searchUrl);
        $xml = simplexml_load_string($sPage->getPage()->getResponseBody(),null , LIBXML_NOCDATA);
        
        foreach ($xml as $singleXmlStore) {
            $eStore = new Marktjagd_Entity_Api_Store();            
            $eStore->setCity($sAddress->extractAddressPart('city', (string) $singleXmlStore->custom2));
            $eStore->setZipcode($sAddress->extractAddressPart('zip', (string) $singleXmlStore->custom2));
            $eStore->setLatitude((string) $singleXmlStore->lat);
            $eStore->setLongitude((string) $singleXmlStore->lng);
            $eStore->setPhone($sAddress->normalizePhoneNumber((string) $singleXmlStore->phone));
            $eStore->setStoreHours($sTimes->generateMjOpenings((string) $singleXmlStore->custom4));
            
            $address = preg_split('#,#', (string) $singleXmlStore->address);
            $eStore->setStreet($sAddress->extractAddressPart('street', $address[0]));
            $eStore->setStreetNumber($sAddress->extractAddressPart('streetnumber', $address[0]));
            
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
