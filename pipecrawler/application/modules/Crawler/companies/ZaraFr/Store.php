<?php
/**
 * Store Crawler für Zara FR (ID: 72365)
 */

class Crawler_Company_ZaraFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.zara.com/';
        $searchUrl = $baseUrl . 'fr/en/stores-locator/search?'
            . 'lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aDays = array(
            1 => 'Su',
            2 => 'Mo',
            3 => 'Tu',
            4 => 'We',
            5 => 'Th',
            6 => 'Fr',
            7 => 'Sa'
        );

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5, 'fr');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores as $singleJStore) {
                if (!preg_match('#FR#', $singleJStore->countryCode)
                    || !preg_match('#Zara#', $singleJStore->kind)) {
                    continue;
                }

                $strTime = '';
                foreach ($singleJStore->openingHours as $singleDay) {
                    if (!count($singleDay->openingHoursInterval)) {
                        continue;
                    }

                    foreach ($singleDay->openingHoursInterval as $singleIntervall) {
                        if (strlen($strTime)) {
                            $strTime .= ',';
                        }

                        $strTime .= $aDays[$singleDay->weekDay] . ' ' . $singleIntervall->openTime . '-' . $singleIntervall->closeTime;
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->id)
                    ->setSection(implode(', ', $singleJStore->sections))
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStreetAndStreetNumber($singleJStore->addressLines[0], 'fr')
                    ->setCity(ucwords(strtolower($singleJStore->city)))
                    ->setZipcode($singleJStore->zipCode)
                    ->setPhoneNormalized(preg_replace('#\+33#', '0', $singleJStore->phones[0]))
                    ->setStoreHoursNormalized($strTime);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}