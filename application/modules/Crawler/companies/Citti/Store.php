<?php

/**
 * Store Crawler für Citty Markt (ID: 68759)
 */
class Crawler_Company_Citti_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.cittimarkt.de/';
        $searchUrl = $baseUrl . 'die_maerkte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aPayment = array(
            'euros' => 'bar',
            'ec' => 'EC-Karte',
            'visalogo' => 'Visa',
            'mastercard' => 'Mastercard',
            'maestro' => 'Maestro',
            'amex' => 'American Express'
        );

        $aMonthMatch = array(
            '#(jan[a-z]*)#i',
            '#(feb[a-z]*)#i',
            '#(mär[a-z]*)#i',
            '#(apr[a-z]*)#i',
            '#(mai)#i',
            '#(jun[a-z]*)#i',
            '#(jul[a-z]*)#i',
            '#(aug[a-z]*)#i',
            '#(sep[a-z]*)#i',
            '#(okt[a-z]*)#i',
            '#(nov[a-z]*)#i',
            '#(dez[a-z]*)#i'
        );
        
        $aMonthReplace = array(
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december'
        );
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="(die_maerkte/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $storeDetailUrl) {
            $detailUrl = $baseUrl . $storeDetailUrl;

            $sPage->open($detailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#anschrift.+?</strong>(.+?)</p#i';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $detailUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatch[1]);

            $pattern = '#fon:?(\s*<[^>]*>\s*)*([0-9]+?\s*-\s*[^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[2]));
            }

            $pattern = '#fax:?(\s*<[^>]*>\s*)*([0-9]+?\s*-\s*[^<]+?)<#i';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[2]));
            }

            $pattern = '#ffnungszeiten</h3>(.+?)</tbody#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#</strong>[^<]+?([0-9]+\s+Parkplätze)#';
            if (preg_match($pattern, $page, $parkingMatch)) {
                $eStore->setParking($parkingMatch[1]);
            }

            $pattern = '#(verkaufsoffene\s*sonntage\s*([0-9]{4}))</h3>(.+?)</div>\s*</div>\s*</div>\s*</div>\s*<div\s*class="subcClear"#si';
            if (preg_match($pattern, $page, $storeHourNotesListMatch)) {
                $aStoreHourNotes = array();
                $pattern = '#<p[^>]*>\s*(([0-9]{1,2}\.)\s+([A-Z][a-z]+)\s*[^<]*?)\s*</p#';
                if (preg_match_all($pattern, $storeHourNotesListMatch[3], $storeHoursNotesMatches)) {
                    $strStoreHourNotes = $storeHourNotesListMatch[1] . ': ';
                    for ($i = 0; $i < count($storeHoursNotesMatches[1]); $i++) {
                        $aStoreHourNotes[strtotime(preg_replace($aMonthMatch, $aMonthReplace, $storeHoursNotesMatches[2][$i] . ' ' . $storeHoursNotesMatches[3][$i] . $storeHourNotesListMatch[2]))] = $storeHoursNotesMatches[1][$i];
                    }
                    ksort($aStoreHourNotes);
                    $eStore->setStoreHoursNotes($strStoreHourNotes . implode(', ', $aStoreHourNotes));
                }
            }
            
            $pattern = '#(zahlungsmittel/|RTEmagicC_)([^\.]+?)\.jpg#';
            if (preg_match_all($pattern, $page, $paymentMatches)) {
                $strPayment = '';
                foreach ($paymentMatches[2] as $singlePayment) {
                    if (strlen($strPayment)) {
                        $strPayment .= ', ';
                    }
                    $strPayment .= $aPayment[$singlePayment];
                }

                $eStore->setPayment($strPayment);
            }
            
            $pattern = '#dd\s*class="csc-textpic-caption"[^>]*>\s*([A-Z]{3})\s*<#';
            if (preg_match_all($pattern, $page, $currencyMatches)) {
                if (strlen($strPayment)) {
                    $strPayment .= ', ';
                }
                $strPayment .= 'Andere Währungen möglich: ';
                $strPayment .= implode(', ', $currencyMatches[1]);
                $eStore->setPayment($strPayment);
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setWebsite($detailUrl);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
