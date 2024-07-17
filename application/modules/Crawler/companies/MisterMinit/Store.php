<?php

/**
 * Store Crawler für Mister Minit (ID: 29098)
 */
class Crawler_Company_MisterMinit_Store extends Crawler_Generic_Company
{
    public function crawl($companyId) {
        $baseUrl = 'http://www.misterminit.eu/';
        $searchUrl = $baseUrl . '/actions/glueMap/json/allDistance?locale=de_de&country=DE';

        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        
        $sPage->open($searchUrl);
        $jsonStores = $sPage->getPage()->getResponseAsJson();

        foreach ($jsonStores as $jsonStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setLatitude($jsonStore->geometry->coordinates[1]);
            $eStore->setLongitude($jsonStore->geometry->coordinates[0]);
            $eStore->setSubtitle($jsonStore->properties->title);
            $eStore->setPhoneNormalized($jsonStore->properties->phone);
            $aAddress = explode('<br />', $jsonStore->properties->description);
            $eStore->setStreetAndStreetNumber($aAddress[0]);
            $eStore->setZipcodeAndCity($aAddress[1]);

            $aOpeningHours = $jsonStore->properties->openingHours;

            $openings = '';
            $aMapOpenings = array(
                'Sunday' => 'So',
                'Monday' => 'Mo',
                'Tuesday' => 'Di',
                'Wednesday' => 'Mi',
                'Thursday' => 'Do',
                'Friday' => 'Fr',
                'Saturday' => 'Sa'
            );

            foreach ($aOpeningHours as $oOpeningHour) {
                if ($oOpeningHour->day != 'Extra' && $oOpeningHour->openingHours != '') {
                    if (strlen($openings) > 0) {
                        $openings .= ', ';
                    }

                    $openings .= $aMapOpenings[$oOpeningHour->day] . ' ' . $oOpeningHour->openingHours;
                }

                if ($oOpeningHour->day == 'Extra' && $oOpeningHour->openingHours != '') {
                    $eStore->setStoreHoursNotes($openings);
                }

            }

            $eStore->setStoreHoursNormalized($openings);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}