<?php

/*
 * Store Crawler für Hagebau (ID: 294)
 */

class Crawler_Company_Hagebau_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.hagebau.de/';
        $searchUrl = $baseUrl . 'data/locations/?origLat=51.165691&origLng=10.451526&query=&allStores=false';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#hagebaumarkt#', $singleJStore->categories) || !preg_match('#deutschland#i', $singleJStore->country)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $strSections = '';
            $aSections = preg_split('#\s*,\s*#', $singleJStore->categories);

            foreach ($aSections as $singleSection) {
                if (preg_match('#hagebaumarkt#', $singleSection)) {
                    continue;
                }
                if (strlen($strSections)) {
                    $strSections .= ', ';
                }
                $strSections .= $singleSection;
            }

            $pattern = '#store_([0-9]+?)\?#';
            if (preg_match($pattern, $singleJStore->imageUrl, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $eStore->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->address)))
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postal)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                    ->setEmail($singleJStore->email)
                    ->setWebsite($singleJStore->storeUrl . $eStore->getStoreNumber())
                    ->setStoreHours($sTimes->generateMjOpenings(implode(', ', $singleJStore->openingHours->entries)))
                    ->setImage($singleJStore->imageUrl)
                    ->setSection($strSections);


            $sPage->open($singleJStore->storeUrl . $eStore->getStoreNumber());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Fax\.?:?([^<]+?)<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }

            $pattern = '#Serviceleistungen(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>#s';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<span\s*class="is-visible"[^>]*>\s*(.+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $strServices = '';
                    $strPayment = '';
                    $strBonusCards = '';
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#(zahlung|finanzierung)#i', $singleService)) {
                            if (strlen($strPayment)) {
                                $strPayment .= ', ';
                            }
                            $strPayment .= $singleService;
                            continue;
                        }
                        if (preg_match('#karte#i', $singleService)) {
                            if (strlen($strBonusCards)) {
                                $strBonusCards .= ', ';
                            }
                            $strBonusCards .= $singleService;
                            continue;
                        }
                        if (strlen($strServices)) {
                            $strServices .= ', ';
                        }
                        $strServices .= $singleService;
                    }
                    $eStore->setPayment($strPayment)
                            ->setService($strServices)
                            ->setBonusCard($strBonusCards);
                }
            }

            if ($eStore->getStoreNumber() == 109029){
                $eStore->setToilet(true)
                        ->setPayment($eStore->getPayment() . ', Barzahlung');
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
