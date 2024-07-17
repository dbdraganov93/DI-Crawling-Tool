<?php
/**
 * Store Crawler für Intermarché FR (ID: 72320)
 */

class Crawler_Company_IntermarcheFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://fc1.1bis.com/';
        $searchUrl = $baseUrl . 'itm-v2/asp/pois.asp?regId=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();

        $aDays = array(
            'day1' => 'Mo',
            'day2' => 'Di',
            'day3' => 'Mi',
            'day4' => 'Do',
            'day5' => 'Fr',
            'day6' => 'Sa',
            'day7' => 'So'
        );

        $aZipcodes = $sDbGeoRegion->findAllZipCodes('fr');
        $aRegions = [];
        foreach ($aZipcodes as $singleZipcode) {
            $aRegions[substr($singleZipcode, 0, 2)] = '';
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aRegions as $singleRegion => $emptyInfo) {
            try {
                $sPage->open($searchUrl . $singleRegion);
                $jStores = $sPage->getPage()->getResponseAsJson();

                $aServices = array();

                if (!count($jStores->pois)) {
                    continue;
                }

                if (!count($aServices)) {
                    foreach ($jStores->services as $serviceKey => $aServiceValues) {
                        $aServices[$serviceKey] = $aServiceValues->name;
                    }
                }

                foreach ($jStores->pois as $singleJStore) {
                    $strServices = '';
                    foreach ($singleJStore->services as $singleService) {
                        if (!array_key_exists($singleService, $aServices)) {
                            continue;
                        }

                        if (strlen($strServices) >= 400) {
                            break;
                        }
                        if (strlen($strServices)) {
                            $strServices .= ', ';
                        }

                        $strServices .= $aServices[$singleService];
                    }

                    $strTimes = '';
                    foreach ($singleJStore as $infoKey => $singleInfo) {
                        if (!array_key_exists($infoKey, $aDays)) {
                            continue;
                        }

                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }

                        $strTimes .= $aDays[$infoKey] . ' ' . preg_replace('#h#', ':', $singleInfo);
                    }
                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setStoreNumber($singleJStore->id)
                        ->setStreetAndStreetNumber($singleJStore->address1, 'FR')
                        ->setZipcode($singleJStore->zip)
                        ->setCity($singleJStore->city)
                        ->setPhoneNormalized($singleJStore->tel)
                        ->setFaxNormalized($singleJStore->fax)
                        ->setStoreHoursNormalized($strTimes)
                        ->setService($strServices);

                    $cStores->addElement($eStore);
                }
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': connection time out: ' . $singleRegion);
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
