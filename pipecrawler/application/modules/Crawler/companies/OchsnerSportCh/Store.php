<?php

/**
 * Store Crawler für Ochsner Sport (CH) (ID: 72164)
 */
class Crawler_Company_OchsnerSportCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.ochsnersport.ch';
        $searchUrl = $baseUrl . '/de/shop/store-finder';

        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<storefinder[^>]*data\-store\-data\-url\-encoded="([^"]+)"#', $page, $matchStores)) {
            throw new Exception('couldn\'t find stores for company ' . $companyId . ' on url ' . $searchUrl);
        }

        $jsonStores = json_decode(urldecode($matchStores[1]));

        foreach ($jsonStores as $jsonStore) {
            $eStore = new Marktjagd_Entity_Api_Store();        
           
            $eStore->setStreetAndStreetNumber($jsonStore->address->line1, 'CH')
                    ->setZipcode($jsonStore->address->postalCode)
                    ->setCity($jsonStore->address->town)
                    ->setSubtitle($jsonStore->address->line2)
                    ->setLatitude($jsonStore->geoPoint->latitude)
                    ->setLongitude($jsonStore->geoPoint->longitude)
                    ->setPhoneNormalized($jsonStore->address->phone)
                    ->setFaxNormalized($jsonStore->address->fax)
                    ->setStoreNumber($jsonStore->address->id)
                    ->setWebsite($baseUrl . $jsonStore->url);

            $sOpening = '';
            foreach ($jsonStore->openingHours->weekDayOpeningList as $weekday) {
                if ($weekday->closed) {
                    continue;
                }

                if (strlen($sOpening)) {
                    $sOpening .= ', ';
                }

                $sOpening .= $weekday->weekDay . ' ' . $weekday->openingTime->formattedHour . '-'
                    . $weekday->closingTime->formattedHour;
            }

            $eStore->setStoreHoursNormalized($sOpening);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}