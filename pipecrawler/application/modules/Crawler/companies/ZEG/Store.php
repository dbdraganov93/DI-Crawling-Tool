<?php

/* 
 * Store Crawler für ZEG (ID: 28944)
 */

class Crawler_Company_ZEG_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://haendlersuche.zeg.com/';
        $searchUrl = $baseUrl . 'haendlersuche.aspx?Lat=50&Lng=10&Umkreis=500&Datatype=JSONP&Lkz=D';
        $sPage = new Marktjagd_Service_Input_Page();

        $aDays = [
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        ];

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#func\((.+)\);$#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->NewDataSet->Table as $singleJStore) {
            $strTimes = '';
            foreach ($singleJStore->Oeffnungszeit->Sommer->Tag as $singleDay) {
                if (!$singleDay->Von1) {
                    continue;
                }
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $aDays[$singleDay->{'@Nr'}] . ' ' . $singleDay->Von1 . '-' . $singleDay->Bis1;

                if ($singleDay->Von2) {
                    $strTimes .= ',' . $aDays[$singleDay->{'@Nr'}] . ' ' . $singleDay->Von1 . '-' . $singleDay->Bis1;
                }
            }

            if (!strlen($strTimes)) {
                foreach ($singleJStore->Oeffnungszeit->Winter->Tag as $singleDay) {
                    if (!$singleDay->Von1) {
                        continue;
                    }
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $aDays[$singleDay->{'@Nr'}] . ' ' . $singleDay->Von1 . '-' . $singleDay->Bis1;

                    if ($singleDay->Von2) {
                        $strTimes .= ',' . $aDays[$singleDay->{'@Nr'}] . ' ' . $singleDay->Von1 . '-' . $singleDay->Bis1;
                    }
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->KdNr)
                ->setZipcode($singleJStore->PLZ)
                ->setCity($singleJStore->ORT)
                ->setStreetAndStreetNumber($singleJStore->Strasse)
                ->setPhoneNormalized(preg_replace('#(\/.+)#', '', $singleJStore->Telefon))
                ->setFaxNormalized($singleJStore->Telefax)
                ->setEmail($singleJStore->EMail)
                ->setLatitude($singleJStore->Latitude)
                ->setLongitude($singleJStore->Longitude)
                ->setStoreHoursNormalized($strTimes);

            if (strlen($eStore->getZipcode()) != 5
                || $eStore->getLatitude() < 47.2
                || $eStore->getLatitude() > 55.2
                || $eStore->getLongitude() < 5.8
                || $eStore->getLongitude() > 15.2) {
                continue;
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}