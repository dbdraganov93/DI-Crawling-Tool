<?php

/**
 * Standortcrawler für Strauss Innovation (ID: 22240)
 *
 * Class Crawler_Company_Strauss_Store
 */
class Crawler_Company_Strauss_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     *
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.strauss-innovation.de/';
        $searchUrl = 'https://storelocator.fortuneglobe.eu/companies/8/stores';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $aMapDays = array(
            'mon' => 'Mo',
            'tue' => 'Di',
            'wed' => 'Mi',
            'thu' => 'Do',
            'fri' => 'Fr',
            'sat' => 'Sa',
            'sun' => 'So'
        );
        
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $aStores = json_decode($page);        
        
        foreach ($aStores as $oStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($oStore->id);
            $eStore->setPhone($sAddress->normalizePhoneNumber($oStore->contact->phone));
            $eStore->setFax($sAddress->normalizePhoneNumber($oStore->contact->fax));
            $eStore->setEmail($oStore->contact->email);
            $eStore->setWebsite($oStore->contact->www);
            
            $sOpenings = '';
            foreach ($oStore->contact->opening_times as $key => $value)
            {
                if (strlen($sOpenings)) {
                    $sOpenings .= ', ';
                }
                
                $sOpenings .= $aMapDays[$key] . ' ' . $value;
            }
            
            $eStore->setStoreHours($sOpenings);
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $oStore->geolocation->address->street));
            $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', $oStore->geolocation->address->street));
            $eStore->setZipcode($oStore->geolocation->address->postal_code);
            $eStore->setCity($oStore->geolocation->address->locality);
            $eStore->setLatitude($oStore->geolocation->geocoordinate->latitude);
            $eStore->setLongitude($oStore->geolocation->geocoordinate->longitude);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}