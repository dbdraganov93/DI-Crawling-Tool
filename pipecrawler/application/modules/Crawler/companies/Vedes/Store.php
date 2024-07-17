<?php

/* 
 * Store Crawler für Vedes and Vedes AT (ID: 28654, 82522)
 */

class Crawler_Company_Vedes_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $aCountry = [
            28654 => 'DE',
            82522 => 'AT',
        ];

        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfos = $sGSRead->getFormattedInfos('19qO67wEZzDgjV9ygVIeHmDQ-4Fvt4NgQPxkIHFof4LI', 'A1', 'AH', 'Budgets for each store');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aInfos as $singleRow) {
            if (!preg_match('#' . $aCountry[$companyId] . '#', $singleRow['Land'])) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['Standort ID'])
                ->setTitle(trim($singleRow['Filialname']))
                ->setStreetAndStreetNumber($singleRow['Straße + Hausnummer'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setDefaultRadius(preg_replace('#\s*km#', '', $singleRow['Radius']))
                ->setStoreHoursNormalized($singleRow['Opening hours'])
                ->setPhoneNormalized($singleRow['Telephone Number'])
                ->setEmail($singleRow['Email ']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}