<?php

/**
 * Store Crawler für Real (ID: 15)
 */
class Crawler_Company_Real_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://webservices.real.de/';
        $searchUrl = $baseUrl . 'v4/realmaerkte/?mode=full';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $xmlStores = simplexml_load_string($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores->list->li as $singleXmlStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string)$singleXmlStore->bkz)
                ->setDistribution((string)$singleXmlStore->region_d)
                ->setPhoneNormalized($singleXmlStore->telefon_vw)
                ->setFaxNormalized($singleXmlStore->telefax_vw)
                ->setStreetAndStreetNumber($singleXmlStore->strasse)
                ->setZipcode((string)$singleXmlStore->plz)
                ->setCity($singleXmlStore->ort)
                ->setLatitude((string)$singleXmlStore->breite)
                ->setLongitude((string)$singleXmlStore->laenge)
                ->setStoreHoursNormalized($singleXmlStore->oeffnung);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
