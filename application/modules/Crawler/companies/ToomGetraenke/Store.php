<?php

/**
 * Store Crawler für Toom Getränkemarkt (ID: 69546)
 */
class Crawler_Company_ToomGetraenke_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.toom-getraenkemarkt.de/';
        $searchUrl = $baseUrl . 'index.php?option=com_storelocator&view=map&format=raw&searchall=1&lat=50&lng=10&radius=1000';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $xmlStores = new SimpleXMLElement($page, LIBXML_NOCDATA);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores->marker as $singleXmlStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $aAddress = preg_split('#\s*,\s*#', (string) $singleXmlStore->address);

            Zend_Debug::dump($singleXmlStore);
            $eStore->setAddress($aAddress[0], $aAddress[1])
                    ->setLatitude((string) $singleXmlStore->lat)
                    ->setLongitude((string) $singleXmlStore->lng)
                    ->setPhoneNormalized((string) $singleXmlStore->phone)
                    ->setStoreHoursNormalized((string) $singleXmlStore->custom4)
                    ->setStoreNumber((string) $singleXmlStore->custom1);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
