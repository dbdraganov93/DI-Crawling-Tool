<?php
/**
 * Store Crawler für Sport 2000 FR (ID: )
 */

class Crawler_Company_Sport2000Fr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sport2000.fr/';
        $searchUrl = $baseUrl . 'feed?catid=15_6_4_14_5_2_3&lang=en&searchall=1';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $xmlStores = simplexml_load_string($page, NULL, LIBXML_NOCDATA);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores->marker as $singleXmlStore) {
            $aAddress = preg_split('#\s*;\s*#', $singleXmlStore->address);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($aAddress[0], 'fr')
                ->setZipcode($aAddress[1])
                ->setCity(ucwords(strtolower($aAddress[2])))
                ->setLatitude($singleXmlStore->lat)
                ->setLongitude($singleXmlStore->lng)
                ->setPhoneNormalized($singleXmlStore->phone)
                ->setEmail($singleXmlStore->email);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}