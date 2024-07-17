<?php

/**
 * Store Crawler für Hypo-Vereinsbank (ID: 71654)
 */
class Crawler_Company_HypoVereinsbank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://app.wigeogis.com/';
        $searchUrl = $baseUrl . 'kunden/hvb/welcome/getGeocodeResults';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aDays = array(
            'mo',
            'di',
            'mi',
            'do',
            'fr',
            'sa'
        );
        
        $aServices = array(
            'sb_einzahler' => 'SB-Einzahler',
            'sb_terminal' => 'SB-Terminal',
            'geldautomat' => 'Geldautomat',
            'gst_zaehlung' => 'GST-Zählung',
            'kontoauszugsdrucker' => 'Kontoauszugsdrucker',
        );

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 5; $i <= 17; $i += 0.1) {
            for ($j = 45; $j <= 56; $j += 0.1) {
                $sPage->open($searchUrl, array('xco' => $i, 'yco' => $j));
                $jStores = $sPage->getPage()->getResponseAsJson();
                foreach ($jStores as $singleJStore) {
                    $strTimes = '';
                    foreach ($aDays as $singleDay) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $singleDay . ' ' . $singleJStore->{$singleDay . '_vorm_von'}
                        . ' - ' . $singleJStore->{$singleDay . '_vorm_bis'};
                        if (property_exists($singleJStore, $singleDay . '_nachm_von')) {
                            $strTimes .= ',' . $singleDay . ' ' . $singleJStore->{$singleDay . '_nachm_von'}
                            . ' - ' . $singleJStore->{$singleDay . '_nachm_bis'};
                        }
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();
                    
                    if ($singleJStore->rollstuhlgerecht == 1) {
                        $eStore->setBarrierFree(true);
                    }
                    
                    $strServices = '';
                    foreach ($aServices as $singleServiceKey => $singleServiceValue) {
                        if ($singleJStore->{$singleServiceKey} == 1) {
                            if (strlen($strServices)) {
                                $strServices .= ', ';
                            }
                            $strServices .= $singleServiceValue;
                        }
                    }
                    $eStore->setStoreNumber($singleJStore->id)
                            ->setSubtitle($singleJStore->title)
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->strasse)))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->strasse)))
                            ->setCity($singleJStore->ort)
                            ->setZipcode($singleJStore->zip)
                            ->setPhone($sAddress->normalizePhoneNumber($singleJStore->telefon))
                            ->setFax($sAddress->normalizePhoneNumber($singleJStore->fax))
                            ->setStoreHours($sTimes->generateMjOpenings($strTimes))
                            ->setStoreHoursNotes($singleJStore->kommentar)
                            ->setService($strServices);

                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
