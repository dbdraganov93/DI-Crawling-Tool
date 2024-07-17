<?php

/**
 * Store Crawler für Euromobil (ID: 28655)
 */
class Crawler_Company_Euromobil_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.euromobil.de/';
        $searchUrl = $baseUrl . '?eID=py&cmd=emmap&uid=&address='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '%20Deutschland&coordinates=__&radius=50&rentaltype=undefined&cvw=on&caudi=on&cskoda=on&cseat=on&L=de';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $oPage = $sPage->getPage();
        $oPage->setTimeout(120);
        $sPage->setPage($oPage);

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 5);


        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            usleep(500000);
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if ($jStores->total == 0) {
                continue;
            }

            foreach ($jStores->stations as $singleJStore) {
                if (!preg_match('#deu#i', $singleJStore->Address->country)) {
                    continue;
                }
                
                $strTimes = '';
                if (!is_null($singleJStore->OpeningTime->day)) {
                    foreach ($singleJStore->OpeningTime->day as $singleDay) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $singleDay->weekday . ' ' . $singleDay->from . '-' . $singleDay->until;
                    }
                }

                $strTimes = $sTimes->convertToGermanDays($strTimes);

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($singleJStore->StationID)
                        ->setSubtitle($singleJStore->title)
                        ->setStreet($sAddress->normalizeStreet($singleJStore->Address->street))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($singleJStore->Address->houseno))
                        ->setCity($singleJStore->Address->city)
                        ->setZipcode($singleJStore->Address->zipcode)
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                        ->setFax($sAddress->normalizePhoneNumber($singleJStore->fax))
                        ->setEmail($singleJStore->email)
                        ->setWebsite($singleJStore->website)
                        ->setLatitude($singleJStore->Coordinates->lat)
                        ->setLongitude($singleJStore->Coordinates->lng)
                        ->setStoreHours($sTimes->generateMjOpenings($strTimes));

                if (count((array)$singleJStore->Brands->Brand) > 1) {
                    $eStore->setSection(implode(',', $singleJStore->Brands->Brand));
                } else {
                    $eStore->setSection($singleJStore->Brands->Brand);
                }

                $cStores->addElement($eStore, true);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

}
