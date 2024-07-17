<?php
/**
 * Store Crawler for NKD AT (ID: 73284)
 */

class Crawler_Company_NkdAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www2.nkd.com';
        $searchUrl = $baseUrl . '/filialsuche_ajax.php?ortplz=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '&land=at';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $weekdays = array('mo', 'di', 'mi', 'do', 'fr', 'sa', 'so');

        $aLinks = $sGen->generateUrl($searchUrl, 'zip', 10, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aLinks as $idx => $singleLink) {
            $this->_logger->info('open ' . $singleLink . ' ' . $idx . ' of ' . count($aLinks));

            if (!$sPage->open($singleLink)) {
                throw new Exception ($companyId . ': unable to open store list page. url: ' . $singleLink);
            }

            $jsonContent = $sPage->getPage()->getResponseAsJson();

            if (!($jsonContent->filialen)) {
                continue;
            }

            foreach ($jsonContent->filialen as $singleStore) {
                if ($singleStore->details->renovier_geschlossen == 1) {
                    continue;
                }

                $storeHours = [];
                foreach ($weekdays as $weekday) {
                    $dayKey1from = $weekday . '1_von';
                    $dayKey1to = $weekday . '1_bis';
                    $dayKey2from = $weekday . '2_von';
                    $dayKey2to = $weekday . '2_bis';

                    if ($singleStore->details->$dayKey1from && $singleStore->details->$dayKey1from != '00:00:00'
                        && $singleStore->details->$dayKey1to && $singleStore->details->$dayKey1to != '00:00:00'
                    ) {
                        $storeHours[] = $weekday . ' ' . $this->normalizeHour($singleStore->details->$dayKey1from) . '-' . $this->normalizeHour($singleStore->details->$dayKey1to);
                    }

                    if ($singleStore->details->$dayKey2from && $singleStore->details->$dayKey2from != '00:00:00'
                        && $singleStore->details->$dayKey2to && $singleStore->details->$dayKey2to != '00:00:00'
                    ) {
                        $storeHours[] = $weekday . ' ' . $this->normalizeHour($singleStore->details->$dayKey2from) . '-' . $this->normalizeHour($singleStore->details->$dayKey2to);
                    }

                    if ($singleStore->details->$dayKey1to && $singleStore->details->$dayKey1to == '00:00:00'
                        && $singleStore->details->$dayKey2from && $singleStore->details->$dayKey2from == '00:00:00'
                    ) {
                        $storeHours[] = $weekday . ' ' . $this->normalizeHour($singleStore->details->$dayKey1from) . '-' . $this->normalizeHour($singleStore->details->$dayKey2to);
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleStore->id)
                    ->setZipcode($singleStore->details->plz)
                    ->setCity($singleStore->details->ort)
                    ->setStreet($singleStore->details->strasse)
                    ->setStreetNumber($singleStore->details->hausnr)
                    ->setPhoneNormalized($singleStore->details->telefon)
                    ->setFaxNormalized($singleStore->details->fax)
                    ->setStoreHoursNormalized(implode(',', $storeHours));

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function normalizeHour($hour)
    {
        if (preg_match('#(\d{2}\:\d{2})\:\d{2}#', $hour, $hoursMatch)) {
            return $hoursMatch[1];
        }

        return $hour;
    }
}