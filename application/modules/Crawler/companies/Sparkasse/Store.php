<?php

/**
 * Store Crawler für Sparkasse (ID: 71229)
 */
class Crawler_Company_Sparkasse_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        ini_set('memory_limit', '1G');
        $numPerPage = 400;
        $geoDiff = 4;
        $baseUrl = 'https://www.sparkasse.de/bin/servlets/sparkasse/'
            . 'filialfinderapi?func=get_objects&query=sort%3Ddist'
            . '%26objectsPerPage%3D' . $numPerPage . '%26blzFilter%3DALL'
            . '&latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '000000'
            . '&longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '000000';
        $storeUrl = 'https://www.sparkasse.de/service/filialsuche.html#details/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aSearchUrls = $sGen->generateUrl($baseUrl, 'coords', 0.5);
        $aParentStores = array();
        $aChildStores = array();
        $aSections = array();
        $aToSkip = array(
            'http://www.bw-bank.de' => 1,
            'http://www.sachsenbank.de' => 1,
            'www.lbbw.de' => 1);
        $aHelp = array();

        $i = 0;

        foreach ($aSearchUrls as $singleUrl) {
            $i++;
            echo 'Open URL ' . $i . '/' . count($aSearchUrls) . ': ' . $singleUrl . PHP_EOL;
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores->fifiObject as $singleStore) {
                if (array_key_exists($singleStore->spkUrl, $aToSkip)) {
                    continue;
                } elseif ($singleStore->parentId === 0) {
                    if (array_key_exists($singleStore->id, $aParentStores)) {
                        continue;
                    } else {
                        $aParentStores[$singleStore->id] = $singleStore;
                    }
                } else {
                    if (array_key_exists($singleStore->id, $aChildStores)) {
                        continue;
                    } else {
                        $aChildStores[$singleStore->id] = $singleStore;
                    }
                }
            }
        }

        unset($sPage);
        unset($sGen);
        unset($aSearchUrls);
        unset($jStores);

        $k = 0;
        $l = 0;
        foreach ($aChildStores as $aSingleChildStore) {
            $latChild = preg_replace('#(\d{1,2}\.\d{' . $geoDiff . '}).*#', '$1', $aSingleChildStore->latitude);
            $lngChild = preg_replace('#(\d{1,2}\.\d{' . $geoDiff . '}).*#', '$1', $aSingleChildStore->longitude);
            $latParent = preg_replace('#(\d{1,2}\.\d{' . $geoDiff . '}).*#', '$1', $aParentStores[$aSingleChildStore->parentId]->latitude);
            $lngParent = preg_replace('#(\d{1,2}\.\d{' . $geoDiff . '}).*#', '$1', $aParentStores[$aSingleChildStore->parentId]->longitude);

            if (strcmp($latChild, $latParent) === 0 && strcmp($lngChild, $lngParent) === 0) {
                if (array_key_exists($aSingleChildStore->parentId, $aSections)) {
                    $aSections[$aSingleChildStore->parentId] .= ', ' . $aSingleChildStore->type->groupName;
                } else {
                    $aSections[$aSingleChildStore->parentId] = $aSingleChildStore->type->groupName;
                }
                $k++;
            } else {
                $aParentStores[$aSingleChildStore->id] = $aSingleChildStore;
                $l++;
            }
        }

        unset($aChildStores);
        $i = 0;

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aParentStores as $store) {
            $i++;
            $strTimes = '';
            $strService = '';
            $eStore = new Marktjagd_Entity_Api_Store();
            $aStoreInfo = array(
                'street' => preg_replace('#\snull\s*$#i', '', $sAddress->normalizeStreet($sAddress->extractAddressPart('street', $store->street))),
                'streetnum' => $sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $store->street)),
                'title' => 'Sparkasse - ' . $store->type->name);

            $hash = md5(serialize($aStoreInfo));

            if (!array_key_exists($hash, $aHelp)) {
                $aHelp[$hash] = 1;
                $storeTitle = $aStoreInfo['title'];
            } else {
                $aStoreInfo['title'] = 'SpK. - ' . $store->type->name;
                $hash = md5(serialize($aStoreInfo));
                if (!array_key_exists($hash, $aHelp)) {
                    $aHelp[$hash] = 1;
                    $storeTitle = $aStoreInfo['title'];
                } else {
                    $aStoreInfo['title'] = $store->spkName . ' - ' . $store->type->name;
                    $hash = md5(serialize($aStoreInfo));
                    if (!array_key_exists($hash, $aHelp)) {
                        $aHelp[$hash] = 1;
                        $storeTitle = $aStoreInfo['title'];
                    } else {
                        continue;
                    }
                }
            }

            $eStore->setStoreNumber($store->id)
                ->setTitle($storeTitle)
                ->setWebsite($storeUrl . $store->id)
                ->setCity($store->city)
                ->setStreet(preg_replace('#\snull\s*$#i', '', $sAddress->normalizeStreet($sAddress->extractAddressPart('street', $store->street))))
                ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $store->street)))
                ->setZipcode($store->plz)
                ->setLatitude($store->latitude)
                ->setLongitude($store->longitude)
                ->setDistribution($store->regionalUnion->name)
                ->setEmail($store->email);

            $phone = trim($store->phonePreselection . $store->phone);
            $fax = trim($store->faxPreselection . $store->fax);

            if (strlen($phone) > 2) {
                if (substr($phone, 0, 1) === 0) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phone));
                } else {
                    $eStore->setPhone($sAddress->normalizePhoneNumber('0' . $phone));
                }
            }

            if (strlen($fax) > 2) {
                if (substr($fax, 0, 1) === 0) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($fax));
                } else {
                    $eStore->setFax($sAddress->normalizePhoneNumber('0' . $fax));
                }
            }

            if (property_exists($store, 'facility')
                && count($store->facility)) {
                sort($store->facility);
                foreach ($store->facility as $singleService) {
                    if (strlen($strService . ', ' . $singleService->name) >= 495) {
                        $strService .= '...';
                        break;
                    }
                    if (strlen($strService)) {
                        $strService .= ', ';
                    }
                    $strService .= trim($singleService->name);
                }
                $eStore->setService($strService);
            }

            if (property_exists($store, 'attribute')) {
                foreach ($store->attribute as $singleAttribute) {
                    if ($singleAttribute->id === 12) {
                        $eStore->setBarrierFree(true);
                    }
                }
            }

            if (is_array($store->formatedOpeningTimeString)) {
                foreach ($store->formatedOpeningTimeString as $singleTime) {
                    if (strlen($strTimes)) {
                        $strTimes .= ', ';
                    }
                    $strTimes .= $sTimes->generateMjOpenings($singleTime, 'text', true);
                }
            } else {
                $strTimes = $sTimes->generateMjOpenings($store->formatedOpeningTimeString, 'text', true);
            }

            $eStore->setStoreHours($strTimes)
                ->setText('Bankleitzahl (BLZ): ' . $aSingleChildStore->blz);

            if (array_key_exists($store->id, $aSections)) {
                $eStore->setSection($aSections[$store->id]);
            }

            $cStores->addElement($eStore, TRUE);

            unset($store);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
