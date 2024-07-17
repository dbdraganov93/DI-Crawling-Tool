<?php

/*
 * Store Crawler für Spar CH (ID: 72172)
 */

class Crawler_Company_SparCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.spar.ch/';
        $searchUrl = $baseUrl . '_json_market_data/spar-maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#CH#', $singleJStore->countryIso)
                || is_object($singleJStore->markettype)
                || preg_match('#maxi#i', $singleJStore->markettype)) {
                continue;
            }

            $strStoreHours = '';
            if (count($singleJStore->openingHours)) {
                foreach ($singleJStore->openingHours as $singleTime) {
                    if (strlen($strStoreHours)) {
                        $strStoreHours .= ',';
                    }
                    $strStoreHours .= preg_replace('#\s*bis\s*#', '-', $singleTime->pretext) . ' ' . preg_replace(array('#\s*Uhr\s*#', '# #'), array('', ' '), $singleTime->text);
                    if (strlen($singleTime->posttext)) {
                        $strStoreHours .= ',' . preg_replace('#\s*bis\s*#', '-', $singleTime->pretext) . ' ' . preg_replace(array('#\s*Uhr\s*#', '# #'), array('', ' '), $singleTime->posttext);
                    }
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->uid)
                ->setStreetAndStreetNumber($singleJStore->street_number, 'CH')
                ->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city)
                ->setPhoneNormalized($singleJStore->telephone)
                ->setFaxNormalized($singleJStore->fax)
                ->setEmail($singleJStore->email)
                ->setWebsite($singleJStore->url)
                ->setStoreHoursNormalized($strStoreHours);

            if (is_float($singleJStore->coordinatex)) {
                $eStore->setLatitude($singleJStore->coordinatex);
            }

            if (is_float($singleJStore->coordinatey)) {
                $eStore->setLongitude($singleJStore->coordinatey);
            }

            if (!$cStores->addElement($eStore)) {
                Zend_Debug::dump($eStore);
                die;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
