<?php

/*
 * Store Crawler für BayWa, Hellweg, HellwegAt (ID: 69602, 72463)
 */

class Crawler_Company_Baywa_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseFacts = $this->getRightFacts($companyId);
        $baseUrl = $baseFacts['url'];
        $searchUrl = $baseUrl . '/markt/';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sTime = new Marktjagd_Service_Text_Times();

        $week = 'next';
        $weekNr = $sTime->getWeekNr($week);

        $newStoreDistribution = [];

        $localFolder = $sFtp->connect('69602', true);

        $sFtp->changedir('KW' . $weekNr . '_mit_Linkouts');
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Beilagenzuordnung.xls#', $singleFile)) {
                $localXlsFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        // this will set new distributions if the file is found
        if (!empty($localXlsFile) && $companyId == 69602) {
            $excelDistributions = $sExcel->readFile($localXlsFile)->getElement(0)->getData();

            $isBayWaKeyFound = false;
            foreach ($excelDistributions as $excelValues) {
                if (empty($excelValues[0])) {
                    continue;
                }

                if (preg_match('#BayWa Bau & Garten#', $excelValues[0])) {
                    $isBayWaKeyFound = true;
                    continue;
                }

                if ($isBayWaKeyFound) {
                    if (preg_match('#_VS\.pdf#', $excelValues[1])) {
                        $distribution = 'VOLLSORTIMENTER';
                    } else if (preg_match('#_NVGO\.pdf#', $excelValues[1])) {
                        $distribution = 'NVGO';
                    } else if (preg_match('#_VSUG\.pdf#', $excelValues[1])) {
                        $distribution = 'VSUG';
                    }

                    // [$city => $distribution]
                    $newStoreDistribution[$excelValues[2]] = $distribution;
                }
            }
        }

        // init crawler
        $sPage->open($searchUrl);
        $response = $sPage->getPage()->getResponseBody();
        preg_match_all('#href=\"\/markt?\/{1,2}([^\/]*)#i', $response, $matchedOne);
        $result = $matchedOne[1];

        foreach ($result as $city) {
            $sPage = new Marktjagd_Service_Input_Page();

            if ($city == 'kirchdorf')
            {
                $city = 'kirchdorf-an-der-krems';
            }

            $result = $sPage->getDomElsFromUrlByClass($baseUrl . '/markt/' . $city . '/', 'cms-element-hellweg-market');
            $result = $result[0]->textContent;
            preg_match('#Öffnungszeiten([\s\nA-z\d:-]*)Adresse#', $result, $openingHours);
            $data['openingHours'] = trim(preg_replace('#\s+#', ' ', $openingHours[1]));


            preg_match('#Adresse([<\/A-z>\n\s\dß=":.?%-ß]*)Anfahrt#', $result, $address);
            $adressData = preg_replace('# {2,}#', '', $address[1]);
            $str = implode("\n", array_filter(explode("\n", $adressData)));
            $data['address'] = preg_split('#\n#', $str);

            preg_match('#Kontakt([\s\nA-z\d:\-\.\+]*)Kontakt#', $result, $phone);
            $phone = (preg_replace('# #', '', $phone[1]));
            $str = implode("\n", array_filter(explode("\n", $phone)));
            $str = preg_replace('#fax:#i', '', $str);
            $data['phone'] = preg_split('#\n#', $str);


            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setPhoneNormalized($data['phone'][1])
                ->setImage($baseFacts['picture'])
                ->setFax($data['phone'][2])
                ->setWebsite($baseUrl . '/markt/' . $city . '/')
                ->setZipcodeAndCity($data['address'][0])
                ->setStreetAndStreetNumber($data['address'][1])
                ->setStoreHoursNormalized($data['openingHours'])
                ->setTitle($baseFacts['title'] . $eStore->getCity())
                ->setBonusCard($baseFacts['card']);

            # Weinsberg is a special case in regards to zipcode
            if($eStore->getZipcode() == '74189')
                $eStore->setZipcode('74248');

            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores);
    }


    /**
     * @param int $companyId
     * @return mixed
     */
    public function getRightFacts(int $companyId): array
    {
        $baseInfos = [];
        switch($companyId)
        {
            case 69602:
                $baseInfos['url'] = 'https://www.baywa-baumarkt.de';
                $baseInfos['picture'] = 'https://www.baywa-baumarkt.de/bundles/hellwegthemedefault/assets/img/market-header-baywa-img.jpg';
                $baseInfos['title'] = 'BayWa Bau- & Gartenmärkte: ';
                $baseInfos['card'] = 'BayWa-Card';
                break;
            case 28323:
                $baseInfos['url'] = 'https://www.hellweg.de';
                $baseInfos['picture'] = 'https://www.hellweg.de/media/dd/e0/40/1636979282/marktfinder%20HWD.jpg';
                $baseInfos['title'] = 'Hellweg Bau- & Gartenmärkte: ';
                $baseInfos['card'] = 'Hellweg-Card';
                break;
            case 72463:
                $baseInfos['url'] = 'https://www.hellweg.at';
                $baseInfos['picture'] = 'https://www.hellweg.at/media/dd/e0/40/1636979282/marktfinder%20HWD.jpg';
                $baseInfos['title'] = 'Hellweg Bau- & Gartenmärkte: ';
                $baseInfos['card'] = 'Hellweg-Card';
                break;
        }
        return $baseInfos;
    }

}
