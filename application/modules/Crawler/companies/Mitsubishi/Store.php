<?php

/**
 * Store Crawler für Mitsubishi (ID: 71686)
 */
class Crawler_Company_Mitsubishi_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mitsubishi-motors.de/';
        $searchUrl = $baseUrl . 'layouts/Handlers/ValidateSearchForDealer.ashx';
        $detailUrl = $baseUrl . 'layouts/Handlers/GetDealerContactAndHour.ashx';

        $searchParams = array(
            'DealerID' => '0',
            'LanguageID' => '1031',
            'SrcLat' => '50.98476789999999',
            'SrcLng' => '11.0298799'
        );

        $detailParams = array(
            'LanguageID' => '1031',
        );

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl, $searchParams);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore->Lat)
                    ->setLongitude($singleJStore->Long)
                    ->setSubtitle($singleJStore->DealerName)
                    ->setText($singleJStore->Description)
                    ->setStreet($sAddress->extractAddressPart('street', $singleJStore->Street))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $singleJStore->Street))
                    ->setCity($singleJStore->City)
                    ->setZipcode($singleJStore->PostCode);

            $services = array();
            foreach ($singleJStore->Services as $service) {
                $services[] = $service->ServiceName;
            }
            $eStore->setService(implode(', ', $services));

            $detailParams['ContactContentID'] = $singleJStore->ContactContentID;
            $detailParams['WeekdaysContentID'] = $singleJStore->WeekdaysContentID;
            $detailParams['WorkingHourContentID'] = $singleJStore->WorkingHourContentID;
            
            try {
                $sPage->open($detailUrl, $detailParams);
                $jDetailStore = $sPage->getPage()->getResponseAsJson();
                $eStore->setStoreNumber($jDetailStore->Contacts[0]->DealerID);

                foreach ($jDetailStore->Contacts as $contact) {
                    switch ($contact->ContactType) {
                        case 1:
                            $eStore->setPhone($contact->ContactValue);
                            break;
                        case 2:
                            $eStore->setFax($contact->ContactValue);
                            break;
                        case 3:
                            $eStore->setEmail($contact->ContactValue);
                            break;
                        case 4:
                            $eStore->setWebsite($contact->ContactValue);
                            break;
                        case 9:
                            if (!strlen($eStore->getPhone())) {
                                $eStore->setPhone($contact->ContactValue);
                            }
                            break;
                        case 10:
                            if (!strlen($eStore->getFax())) {
                                $eStore->setFax($contact->ContactValue);
                            }
                            break;
                        case 11:
                            if (!strlen($eStore->getEmail())) {
                                $eStore->setEmail($contact->ContactValue);
                            }
                            break;
                        case 12:
                            if (!strlen($eStore->getWebsite())) {
                                $eStore->setWebsite($contact->ContactValue);
                            }
                            break;
                    }
                }

                $storeHours = array();
                foreach ($jDetailStore->Shifts as $shifts) {
                    if (preg_match('#[0-9]{2}#', $shifts->Shift3)) {
                        $storeHours[] = substr($shifts->DayName, 0, 2) . ' ' . $shifts->Shift3;
                    }

                    if (strlen($shifts->Shifts4)) {
                        $storeHours[] = ',' . substr($shifts->DayName, 0, 2) . ' ' . $shifts->Shift4;
                    }

                    if (!preg_match('#[0-9]{2}#', $shifts->Shift3)) {
                        if (preg_match('#[0-9]{2}#', $shifts->Shift1)) {
                            $storeHours[] = substr($shifts->DayName, 0, 2) . ' ' . $shifts->Shift1;
                        }

                        if (strlen($shifts->Shifts2)) {
                            $storeHours[] = ',' . substr($shifts->DayName, 0, 2) . ' ' . $shifts->Shift2;
                        }
                    }
                }

                $eStore->setStoreHours(implode(',', $storeHours));
            } catch (Exception $e) {
                $this->_logger->info('Exception abgefangen: ' . $e->getMessage());
            }

            $cStores->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
