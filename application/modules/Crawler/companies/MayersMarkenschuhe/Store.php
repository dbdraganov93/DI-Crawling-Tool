<?php

/**
 * Storecrawler für Mayer's Markenschuhe (ID: 29029)
 */
class Crawler_Company_MayersMarkenschuhe_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();
        
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        foreach ($sFtp->listFiles('.', '#storeList\.xls#') as $singleStoreFile) {
            $localStorePath = $sFtp->downloadFtpToDir($singleStoreFile, $localPath);
            break;
        }
        
        $aStoreData = $sExcel->readFile($localStorePath, TRUE)->getElement(0)->getData();
        
        $aAdditionalStoreInfos = array();
        foreach ($aStoreData as $singleStoreData) {
            $aAdditionalStoreInfos[$singleStoreData['Beschreibung'] . '|' . str_pad($singleStoreData['PLZ'], 5, '0', STR_PAD_LEFT) . '|' . $singleStoreData['Straße']]['hushPuppies'] = $singleStoreData['Mit Hush Puppies Shop'];
            $aAdditionalStoreInfos[$singleStoreData['Beschreibung'] . '|' . str_pad($singleStoreData['PLZ'], 5, '0', STR_PAD_LEFT) . '|' . $singleStoreData['Straße']]['tamarisShop'] = $singleStoreData['Mit Tamaris Shop'];
            $aAdditionalStoreInfos[$singleStoreData['Beschreibung'] . '|' . str_pad($singleStoreData['PLZ'], 5, '0', STR_PAD_LEFT) . '|' . $singleStoreData['Straße']]['markenSport'] = $singleStoreData['Mit Markensporttextilien'];
            $aAdditionalStoreInfos[$singleStoreData['Beschreibung'] . '|' . str_pad($singleStoreData['PLZ'], 5, '0', STR_PAD_LEFT) . '|' . $singleStoreData['Straße']]['brochureVersion'] = $singleStoreData['Prospekt Mutation'];
        }
        
        $page = $sPage->getPage();
        $page->setMethod('POST')
                ->setAlwaysHtmlDecode(false);
        $sPage->setPage($page);

        $geoSteps = 0.5;
        $southLat = 47.200;     // 47.2701270
        $northLat = 55.200;     // 55.081500
        $westLong = 5.800;      // 5.8663566
        $eastLong = 15.200;     // 15.0418321

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
            for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                $aParams = array(
                    'formdata' => 'addressInput=',
                    'address' => '',
                    'lat' => $lat,
                    'lng' => $long,
                    'name' => '',
                    'radius' => '1000',
                    'action' => 'csl_ajax_search',
                    'type' => 'search',
                );

                $sPage->open('http://www.mayers-markenschuhe.de/wp-admin/admin-ajax.php', $aParams);
                $result = json_decode($page->getResponseBody(), true);

                if (count($result['response'])) {
                    foreach ($result['response'] as $store) {
                        $eStore = new Marktjagd_Entity_Api_Store();
                        $eStore->setStreetAndStreetNumber($store['address'])
                                ->setZipcode($store['zip'])
                                ->setCity($store['city'])
                                ->setLatitude($store['lat'])
                                ->setLongitude($store['lng'])
                                ->setPhoneNormalized($store['phone'])
                                ->setFaxNormalized($store['fax'])
                                ->setStoreHoursNormalized($store['hours'])
                                ->setStoreNumber($store['id']);

                        if ($eStore->getStoreNumber() <= 7) {
                            $eStore->setTitle('Tamaris Store');
                        }
                        
                        foreach ($aAdditionalStoreInfos as $singleKey => $singleAdditionalInfos) {
                            $aIdentifiers = preg_split('#\|#', $singleKey);
                            $strStreet = $sAddress->normalizeStreet($aIdentifiers[2]) . ' ' . $sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aIdentifiers[2]));
                            
                            $strTitle = $eStore->getTitle();
                            if (!strlen($eStore->getTitle())) {
                                $strTitle = 'Mayer´s Markenschuhe';
                            }
                            if ($aIdentifiers[0] == $strTitle
                                    && $aIdentifiers[1] == $eStore->getZipcode()
                                    && $strStreet == $eStore->getStreet() . ' ' . $eStore->getStreetNumber()) {
                                $strSections = '';
                                if (!is_null($singleAdditionalInfos['hushPuppies'])) {
                                    if (strlen($strSections)) {
                                        $strSections .= ', ';
                                    }
                                    $strSections .= $singleAdditionalInfos['hushPuppies'];
                                }
                                
                                if (!is_null($singleAdditionalInfos['tamarisShop'])) {
                                    if (strlen($strSections)) {
                                        $strSections .= ', ';
                                    }
                                    $strSections .= $singleAdditionalInfos['tamarisShop'];
                                }
                                
                                if (!is_null($singleAdditionalInfos['markenSport'])) {
                                    if (strlen($strSections)) {
                                        $strSections .= ', ';
                                    }
                                    $strSections .= $singleAdditionalInfos['markenSport'];
                                }
                                
                                $eStore->setDistribution($singleAdditionalInfos['brochureVersion'])
                                        ->setSection($strSections);
                            }
                        }

                        if($cStores->addElement($eStore)) {
                            $this->_logger->info('store added.');
                        }
                    }
                }
            }
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
