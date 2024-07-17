<?php

/*
 * Store Crawler für Nissan (ID: 71430)
 */

class Crawler_Company_Nissan_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.nissan.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setTimeout(60);
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        $searchUrl = $baseUrl . 'content/nissan/de_DE/index/dealer-finder/jcr:content/freeEditorial/contentzone_e70c/columns/columns12_5fe8/col1-par/find_a_dealer_14d.extended_dealers_by_location.json/_charset_/utf-8/page/1/size/1000/location/65445/data.json';
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        foreach ($jStores->dealers as $singleJStore) {
            if (!preg_match('#de#', $singleJStore->country)) {
                continue;
            }

            $strSections = '';
            foreach ($singleJStore->dealerServices as $singleSection) {
                if (strlen($strSections)) {
                    $strSections .= ', ';
                }

                $strSections .= $singleSection->name;
            }

            $eStore = new Marktjagd_Entity_Api_Store;

            $eStore->setStreetAndStreetNumber(preg_replace(array('#\.[^\s]#', '#\s{2,}#'), array('. ', ' '), $singleJStore->address->addressLine1))
                    ->setCity($singleJStore->address->city)
                    ->setZipcode($singleJStore->address->postalCode)
                    ->setPhoneNormalized($singleJStore->contact->phone)
                    ->setEmail($singleJStore->contact->email)
                    ->setWebsite($singleJStore->contact->website)
                    ->setStoreNumber($singleJStore->dealerId)
                    ->setStoreHoursNormalized($singleJStore->openingHours->openingHoursText)
                    ->setSection($strSections);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
