<?php

/**
 * Store Crawler für Skoda (ID: 68837)
 */
class Crawler_Company_Skoda_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $searchUrl = 'http://dealers.skoda-auto.com/api/107/de-DE/Dealers/Get?DLFindingMethod=StandardFinding&'
            . 'DLBatchSize=5000&DLOffset=0&DLServiceTypes=Sales%2CUsedCarSales%2CMotabilitySales%2C'
            . 'FixedPriceAccessories%2CService%2CNonStopService%2CMotabilityRepairer%2CAdBlueRefuel%2CCNGRefuel%2CLPGRefuel';


        $aWeekDayKeys = array(1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So');

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json->Items as $item) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($item->GlobalId)
                ->setTitle($item->Name)
                ->setWebsite($item->CustomWebsiteUrl)
                ->setZipcode($item->Address->ZipCode)
                ->setLatitude($item->Address->Latitude)
                ->setLongitude($item->Address->Longitude)
                ->setStreetAndStreetNumber($item->Address->Street->Value)
                ->setCity(trim($item->Address->City->Value));

            foreach ($item->GroupServices[0]->OfferedServiceTypes[0]->Contacts as $oContact) {
                if ($oContact->Code == 'E-mail') {
                    $eStore->setEmail($oContact->Value->Value);
                }

                if ($oContact->Code == 'Fax') {
                    $eStore->setFaxNormalized($oContact->Value->Value);
                }

                if ($oContact->Code == 'Phone') {
                    $eStore->setPhoneNormalized($oContact->Value->Value);
                }
            }

            $aOpeningHours = $item->GroupServices[0]->OfferedServiceTypes[0]->OpeningHours;

            $sOpening = '';
            foreach ($aOpeningHours as $oWeekday) {
                if (!empty($oWeekday->Interval1From) && !empty($oWeekday->Interval1To)) {
                    if (strlen($sOpening)) {
                        $sOpening .= ', ';
                    }

                    $sOpening .= $aWeekDayKeys[$oWeekday->WeekDay] . ' '
                        . substr($oWeekday->Interval1From, 0, 5) . '-'
                        . substr($oWeekday->Interval1To, 0, 5);
                }

                if (!empty($oWeekday->Interval2From) && !empty($oWeekday->Interval2To)) {
                    if (strlen($sOpening)) {
                        $sOpening .= ', ';
                    }

                    $sOpening .= $oWeekday->WeekDay . ' '
                        . substr($oWeekday->Interval2From, 0, 5) . '-'
                        . substr($oWeekday->Interval2To, 0, 5);
                }
            }
            $eStore->setStoreHoursNormalized($sOpening);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
