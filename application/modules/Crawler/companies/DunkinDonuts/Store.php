<?php

/*
 * Store Crawler für Dunkin' Donuts (ID: 71780)
 */

class Crawler_Company_DunkinDonuts_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('mbstring.internal_encoding', 'UTF-8');
        $baseUrl = 'https://dunkin-donuts-de.shop-finder.org/';
        $searchUrl = $baseUrl . 'api/stores/search.json?lng=&lat=&radius=all&country_code=DE&address=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->content as $singleJStore)
        {
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $singleJStore->Store->address);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleJStore->Store->id)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 1]))
                    ->setLatitude($singleJStore->Store->latitude)
                    ->setLongitude($singleJStore->Store->longitude)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->Store->phone))
                    ->setFax($sAddress->normalizePhoneNumber($singleJStore->Store->fax))
                    ->setEmail($sAddress->normalizeEmail($singleJStore->Store->email))
                    ->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('# #', '#–#'), array(' ', '-'), $singleJStore->Store->opening_hours), 'text', true));
            
            $cStores->addElement($eStore, true);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
