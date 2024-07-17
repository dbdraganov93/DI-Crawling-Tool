<?php

/*
 * Store Crawler für Kamps (ID: 345)
 */

class Crawler_Company_Kamps_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.kamps.de/';
        $searchUrl = $baseUrl . 'unternehmen/baeckereibackstuben-suche';
        $sPage = new Marktjagd_Service_Input_Page();

        $aCafeTypes = array(
            1 => 'Mit Sitzcafé',
            2 => 'Mit Stehcafé',
            3 => 'Mit Sitz-/Stehcafé'
        );
        
        $aCharstoExchange = array(
            '#\\\u00df#',
            '#\\\\/#',
            '#\\\u00f6#',
            '#\\\u00fc#',
            '#\\\u00e4#',
            '#\\\u00c4#',
            '#\\\u00d6#',
            );
        
        $aCharsExchange = array(
            'ß',
            '/',
            'ö',
            'ü',
            'ä',
            'Ä',
            'Ö'
            );

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#myLatLng\.push\(([^\)]+?)\);#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#"([^"]+?)":"?([^",]+?)"?,#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos - ' . $singleStore);
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#"([^"]+?)(Start|End)Time":\{"date":"\d{4}-\d{2}-\d{2}\s+([^\.]+?)\.#';
            if (preg_match_all($pattern, $singleStore, $timeMatches)) {
                $strTime = '';
                for ($i = 0; $i < count($timeMatches[0]); $i++) {
                    if (strlen($strTime) && !preg_match('#End#', $timeMatches[2][$i])) {
                        $strTime .= ',';
                    }

                    if (preg_match('#End#', $timeMatches[2][$i])) {
                        $strTime .= '-' . $timeMatches[3][$i];
                        continue;
                    }

                    $strTime .= $timeMatches[1][$i] . ' ' . $timeMatches[3][$i];
                }
            }

            $eStore->setStreetAndStreetNumber(preg_replace($aCharstoExchange, $aCharsExchange, $aInfos['street']))
                    ->setCity(preg_replace($aCharstoExchange, $aCharsExchange, $aInfos['city']))
                    ->setZipcode($aInfos['zip'])
                    ->setPhoneNormalized($aInfos['telephone'])
                    ->setStoreHoursNormalized($strTime)
                    ->setSection($aCafeTypes[$aInfos['cafeType']]);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
