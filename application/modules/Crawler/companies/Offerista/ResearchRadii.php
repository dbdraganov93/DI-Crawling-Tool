<?php

class Crawler_Company_Offerista_ResearchRadii extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sAddress = new Marktjagd_Service_Text_Address();

        $aInfos = $sGSRead->getFormattedInfos('199vcEKtMnZlBODdmMcXfXICR1gowG0lCuCBbTGdDjUU', 'A1', 'C', 'cities');

        foreach ($aInfos as $key => $aInfo) {
            $aInfos[$key]['zipcodes'] = [];
            $localCode = 'DE';
            if (strlen($aInfo['countryCode'])) {
                $localCode = $aInfo['countryCode'];
            }
            $zipcodeData = $sDbGeo->findZipCodesForCity($aInfo['city'], $localCode);
            foreach ($zipcodeData as $zipcode => $aGeo) {
                if (in_array($zipcode, $aInfos[$key]['zipcodes'])) {
                    continue;
                }
                $aInfos[$key]['zipcodes'][] = $zipcode;
            }

            if (strlen($aInfo['radius'])) {
                $aZipcodes = $sDbGeo->findAll($localCode);
                foreach ($aZipcodes as $singleZipcode) {
                    if (in_array($singleZipcode->getZipcode(), $aInfos[$key]['zipcodes'])) {
                        continue;
                    }
                    foreach ($zipcodeData as $zipcode => $aGeo) {
                        $distance = $sAddress->calculateDistanceFromGeoCoordinates(
                            (float)$singleZipcode->getLatitude(),
                            (float)$singleZipcode->getLongitude(),
                            (float)$aGeo['latitude'],
                            (float)$aGeo['longitude']);
                        if ($distance <= $aInfo['radius']) {
                            $aInfos[$key]['zipcodes'][] = $singleZipcode->getZipcode();
                        }
                    }
                }
            }
        }

        foreach ($aInfos as $key => $aInfo) {
            $aInfos[$key]['zipcodes'] = implode(',', array_unique($aInfo['zipcodes']));
        }

        array_unshift($aInfos, array_keys($aInfos[0]));

        for ($i = 1; $i < count($aInfos); $i++) {
            $aInfos[$i] = array_values($aInfos[$i]);
        }

        $sGSWrite = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();

        $sGSWrite->writeGoogleSpreadsheet($aInfos, '199vcEKtMnZlBODdmMcXfXICR1gowG0lCuCBbTGdDjUU', FALSE, 'A1', 'cities', FALSE, FALSE);

        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
            ->setIsImport(false);

        return $this->_response;
    }
}