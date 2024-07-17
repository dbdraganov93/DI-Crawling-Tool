<?php

/* 
 * Store Crawler für Levis (ID: 73744)
 */

class Crawler_Company_Levis_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.levi.com/';
        $searchUrl = $baseUrl . 'nextgen-webhooks/?operationName=storeDirectory&locale=DE-de_DE';
        $jPayload = '{"operationName":"storeDirectory","variables":{"countryIsoCode":"DE"},"query":"query storeDirectory($countryIsoCode: String!) {\n  storeDirectory(countryIsoCode: $countryIsoCode) {\n    storeFinderData {\n      addLine1\n      addLine2\n      city\n      country\n      departments\n      distance\n      hrsOfOperation {\n        daysShort\n        hours\n        isOpen\n      }\n      latitude\n      longitude\n      mapUrl\n      phone\n      postcode\n      state\n      storeId\n      storeName\n      storeType\n      todaysHrsOfOperation {\n        daysShort\n        hours\n        isOpen\n      }\n      uom\n    }\n  }\n}\n"}';

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $jStores = json_decode($result)->data->storeDirectory;

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->storeFinderData as $singleJStore) {
            if (!preg_match('#DE#', $singleJStore->country)
                || !preg_match('#(STORE|OUTLET)#', $singleJStore->storeType)) {
                continue;
            }

            $strTimes = '';
            if (count($singleJStore->hrsOfOperation)) {
                foreach ($singleJStore->hrsOfOperation as $singleDay) {
                    if (preg_match('#closed#i', $singleDay->hours)) {
                        continue;
                    }

                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $singleDay->daysShort . ' ' . $singleDay->hours;
                }
            }

            $strSections = '';
            if (count($singleJStore->departments)) {
                foreach ($singleJStore->departments as $singleDepartment) {
                    if (strlen($strSections)) {
                        $strSections .= ', ';
                    }
                    $strSections .= $singleDepartment;
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeId)
                ->setStreetAndStreetNumber($singleJStore->addLine1)
                ->setCity($singleJStore->city)
                ->setZipcode($singleJStore->postcode)
                ->setPhoneNormalized($singleJStore->phone)
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setStoreHoursNormalized($strTimes)
                ->setSection($strSections);

            if (!strlen($eStore->getStoreHours())) {
                $eStore->setText('Derzeit geschlossen.');
            }

            $cStores->addElement($eStore, TRUE);
        }

        return $this->getResponse($cStores);
    }
}