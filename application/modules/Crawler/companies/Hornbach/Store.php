<?php

/**
 * Store Crawler für Hornbach (ID: 60)
 */
class Crawler_Company_Hornbach_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl    = 'http://www.hornbach.de/mvc/market/markets/all';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        if (!count($jStores) > 0) {
            $this->_logger->err('Company ID- ' .  $companyId . ': Unable to get json response for store list.');
            exit;
        } else {
            $this->_logger->info('Company ID- ' .  $companyId . ': ' . count($jStores) . ' stores found.');
        }

        foreach ($jStores as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setZipcode($singleStore->zipCode)
                    ->setLatitude($singleStore->latitude)
                    ->setLongitude($singleStore->longitude)
                    ->setStoreNumber($singleStore->marketCode)
                    ->setCity($singleStore->city)
                    ->setPhone($sAddress->normalizePhoneNumber($singleStore->phone))
                    ->setStreet($sAddress->normalizeStreet($singleStore->streetName))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($singleStore->streetNumber));
            
            $storeHourNotes = '';
            $storeHours = '';
            $count = count($singleStore->atomicOpeningTimes);
            $weekdays = array('Mo ', ', Di ', ', Mi ', ', Do ', ', Fr ', ', Sa ');
            for ($i = 0; $i < $count; $i++) {
                if ($i == 6) {
                    $storeHourNotes = $singleStore->atomicOpeningTimes[$i]->day . ' ';
                    $storeHourNotes .= preg_replace('#.+?(\d\d\:\d\d)#', '$1', $singleStore->atomicOpeningTimes[$i]->from) . '-';
                    $storeHourNotes .= preg_replace('#.+?(\d\d\:\d\d)#', '$1', $singleStore->atomicOpeningTimes[$i]->until);
                }elseif ($i == 7) {
                    $storeHourNotes .= ', ' . $singleStore->atomicOpeningTimes[$i]->day . ' ';
                    $storeHourNotes .= preg_replace('#.+?(\d\d\:\d\d)#', '$1', $singleStore->atomicOpeningTimes[$i]->from) . '-';
                    $storeHourNotes .= preg_replace('#.+?(\d\d\:\d\d)#', '$1', $singleStore->atomicOpeningTimes[$i]->until);
                } else {
                    $storeHours .= $weekdays[$i] . $singleStore->atomicOpeningTimes[$i]->from . '-' . $singleStore->atomicOpeningTimes[$i]->until;
                }
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($storeHours))
                    ->setStoreHoursNotes($storeHourNotes);
            
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
