<?php

use Marktjagd\ApiClient\Resource\Brochure\BrochureResource;

/**
 * Brochure Crawler für Edeka Nord (ID: 73540 - 73541) php testCrawler.php EdekaNord/Brochure 73540
 */
class Crawler_Company_EdekaNord_Brochure extends Crawler_Generic_Company
{

    private $_companyId;
    private $_week;

    private Marktjagd_Service_Output_Pdf $sPdf;

    public function crawl($companyId)
    {
        $this->_companyId = $companyId;
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $this->sPdf = new Marktjagd_Service_Output_Pdf();

        $cStores = $sApi->findStoresByCompany($companyId);
        $aStoreNumbers = [];
        foreach ($cStores->getElements() as $eStore) {
            $aStoreNumbers[] = $eStore->getStoreNumber();
        }

        $companyPrefix = [
            '73540' => 'Marktkauf: ',
            '73541' => 'EDEKA: ',
        ];

        $this->_week = 'next';
        if (date('w') < 4) {
            $this->_week = 'this';
        }
        $kw = $sTimes->getWeekNr($this->_week);
        $yr = $sTimes->getWeeksYear($this->_week);

        $localPath = $sFtp->connect('73541', TRUE);
        $aBrochures = [];
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (!preg_match('#Team\s*Digital#', $singleFolder)) {
                continue;
            }

            $sFtp->changedir($singleFolder);

            foreach ($sFtp->listFiles() as $singleFile) {
                if (preg_match('#KW' . $kw . '_Nord_Handzettel\.csv#', $singleFile)) {
                    $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    break 2;
                }
            }
        }

        $aData = $sPss->readFile($localAssignmentFile, TRUE, ';')->getElement(0)->getData();
        $aDistributions = [];
        foreach ($aData as $singleRow) {
            if (!in_array($singleRow['MARKT_ID'], $aStoreNumbers)) {
                continue;
            }
            $aDistributions[$singleRow['WERBEGEBIET_HZ']][] = $singleRow['MARKT_ID'];
            if ($singleRow['WERBEGEBIET_UHZ']) {
                $aDistributions[$singleRow['WERBEGEBIET_UHZ']][] = $singleRow['MARKT_ID'];
            }
        }

        foreach ($sFtp->listFiles() as $singleFolder) {
            if (!preg_match('#Offerista#', $singleFolder)) {
                continue;
            }

            $sFtp->changedir($singleFolder);

            foreach ($sFtp->listFiles() as $singleFile) {
                if (!preg_match("#KW$kw" . "_([0-9]{6})_([0-9]{6})_(NORD|naturkind)_(.+?)\.pdf#i", $singleFile, $brochureMatch)) {
                    continue;
                }

                if ($brochureMatch[3] == 'naturkind') {
                    $brochureMatch[4] = 'naturkind_' . $brochureMatch[4];
                }

                if (!array_key_exists($brochureMatch[4], $aDistributions)
                    && !preg_match('#Australien#', $brochureMatch[4])
                    && !preg_match('#Offerista#', $brochureMatch[3])) {
                    continue;
                }
                $aBrochures[$brochureMatch[4]] = [
                    'localPath' => $sFtp->downloadFtpToDir($singleFile, $localPath),
                    'visibleStart' => date('d.m.Y', strtotime("monday " . $this->_week . " week -1 day")),
                    'validEnd' => preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.20$3', $brochureMatch[2])
                ];

                $this->_logger->info($companyId . ': found brochure (Offerista folder) for: ' . $brochureMatch[4]);

            }
        }

        $sFtp->changedir('/73541');

        foreach ($sFtp->listFiles() as $singleFolder) {
            if (!preg_match('#Team\s*Digital#', $singleFolder)) {
                continue;
            }

            $sFtp->changedir($singleFolder);

            foreach ($sFtp->listFiles() as $singleFile) {
                if (preg_match("#KW$kw" . "_([0-9]{6})_([0-9]{6})_(NORD|naturkind)_(.+?)\.pdf#i", $singleFile, $brochureMatch)) {

                    if ($brochureMatch[3] == 'naturkind') {
                        $brochureMatch[4] = 'naturkind_' . $brochureMatch[4];
                    }

                    if ((!array_key_exists($brochureMatch[4], $aDistributions)
                            || array_key_exists($brochureMatch[4], $aBrochures)
                            || preg_match('#Offerista_#', $brochureMatch[4]))
                        && !preg_match('#Offerista#', $brochureMatch[4])) {
                        continue;
                    }
                    $aBrochures[$brochureMatch[4]] = [
                        'localPath' => $sFtp->downloadFtpToDir($singleFile, $localPath),
                        'visibleStart' => preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.20$3', $brochureMatch[1]),
                        'validEnd' => preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.20$3', $brochureMatch[2])
                    ];
                    $this->_logger->info($companyId . ': found brochure (Team Digital folder) for: ' . $brochureMatch[4]);
                }
            }
        }
        $sFtp->close();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $specialBrochure = FALSE;
        foreach ($aBrochures as $key => $aInfos) {
            $pdfInfos = $this->sPdf->getAnnotationInfos($aInfos['localPath']);
            if (preg_match('#Offerista#', $key) && count($pdfInfos) == 1) {
                continue;
            } elseif (!preg_match('#Offerista#', $key) && in_array($key, [
                        'EDEKAMV',
                        'EDEKAOSH',
                        'EDEKAWHH',
                        'EDEKAWSH',
                        'FMMV',
                        'FMOSH',
                        'FMWHH',
                        'FMWSH',
                    ]
                )) {
                $aInfos['localPath'] = $this->setGenericClickout($aInfos['localPath']);
            }

            $cBrochures->addElement($this->createBrochureCopy($aInfos, $key, $kw, $yr, $companyPrefix[$companyId], '_DLC'));
            $cBrochures->addElement($this->createBrochureCopy($aInfos, $key, $kw, $yr, $companyPrefix[$companyId], '_DLC_V'));

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setBrochureNumber(substr($key, -20, 20) . '_KW' . $kw . '_' . $yr)
                ->setTitle($companyPrefix[$companyId] . 'Wochenangebote')
                ->setUrl($aInfos['localPath'])
                ->setStart(date('d.m.Y', strtotime($aInfos['visibleStart'] . ' + 1 day')))
                ->setEnd($aInfos['validEnd'])
                ->setVisibleStart($aInfos['visibleStart'])
                ->setVariety('leaflet')
                ->setOptions('no_cut')
                ->setStoreNumber(implode(',', $aDistributions[$key]));

            if (preg_match('#Australien#', $key)) {
                $eBrochure->setStoreNumber('215241,10000346,10000286,8002942,1025698,8430,106733,8432,83344,126825,921298,8878,126779,8001315,188335,8701,8757,9127,8969,892873,99,126829,127067,127026,127074,9065,126660,126700,8421,9105,127074,9122,8793,8779,96671,111102,96671,9138,126686,18367,10000561,6280884,127036,126829,126623,9146,87524,215241,126836,6280646,127572,126860,2643905,8001203,126655,190852,9117,8001203,8000235,8430,8851,126828,126655,17984,17968,127066,8374,127688,126655,106733,126482,127065,8852,92298,8885,126751,126758,9062,127562,83345,8000102,8002156,6014104,8915,126758,8840,8896,127562,8001187,8003119,9007,8001187,8001994,179435,8895,8857,121915,9015,10000683,126862,4515159,100658,9021,127029,8582,8777,278931,8001233,10000299,126645,106733,9017,9005,17186,8465,4171115,81119,8001597,8000734,179236,1234791,113,8995,127754,8636,8565,8860,78602,126832,8620,6280806,10000363,87524,8001676,127036,4451486,8001584,127077,20066,8002016,127039,10000298,190851,17981,20066,18375,8945,8943,126690,125073,17986,127694,106734,127574,8865,112753,9001,8897,111,8856,913875,179234,8000708,127072,112754,8619,8448,8002919,8843,8876,8545,8000000,8896,8832,126692,903750,8001457,8969,205537,127692,179236,8855,127691,553428,126660,8335,126824,126694,127029,8474,126662,6239106,205605,126487,8474,8498,190007,179435,8531,8530,8000955,100669,8697,99,127575,8000174,8001140,8581,8588,8589,8588,127757,8604,913875,8001449,127075,8000600,126781,8656,8673,8729,8723,6177584,111917,126619,9018,10000306,17290,8710,126765,581659,8686,8721,126706,10000286,8000224,8000888,100673,10000897,6179782,17968,8001558,6164302,127069,8003004,121909,8003174,127581,182895,8002122,591974,126655,8625,126765,8000202,8002101,8002101,192,6048995,8872,126692,126819,179234,8707,8001590,8000224,8862,6280830,135,8870,8859,8851,5555583,8998,80183,8845,127689,9065,126856,179235,8003161,78607,190856,8000508,8811,8845,126739,903750,8501,1205918,126778,83346,8000090,8001136,8002386,9093,8001425,8002215,9152,8000050,126699,8001473,179438,126747,6280552,8001472,185343,8003219,8003224,122785,7,17974,8507,17984,913875,205605,106,81119,126861,8701,111100,181007,464079,127771,17981,8661,8673,163941,8000139,100665,86438,8719,8517,8703,8001558,589196,85160,8421,8001990,8002220,291656,8766,188334,1234734,16967,8786,16972,8790,589196,8000074,8001203,6280806,126755,8002977,78601,8731,126823,8001443,8892,8894,8837,126623,1025698,126487,8951,8002101,6281096,8866,8001457,112753,8001233,9085,8992,8001924,126662,126739,8588,127768,8703,8864,6280789,100674,8853,100658,126686,126825,8996,8001449,903750,8330,9146,9166,8650,126702,8001558,126476,97,127041,126660,8001908,126476,127690,9028,8001343,9078,8999,127768,126619,8002199,127041,8362,182878,111100,126703,8844,8861,139748,8895,6164293,8001315');

            }

            $cBrochures->addElement($eBrochure);
        }

        if ($companyId == 73541 && !$specialBrochure) {
            $this->_logger->info($companyId . ': no special brochure found for Edeka Nord');
        }

        return $this->getResponse($cBrochures, $companyId, 5);
    }

    private function createBrochureCopy($aInfos, $key, $kw, $yr, $companyPrefix, $suffix): Marktjagd_Entity_Api_Brochure
    {
        $brochureCopy = preg_replace('#\.pdf#', $suffix . '.pdf', $aInfos['localPath']);
        copy($aInfos['localPath'], $brochureCopy);

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(substr($key, -15, 15) . '_KW' . $kw . '_' . $yr . $suffix)
            ->setTitle($companyPrefix . 'Wochenangebote')
            ->setUrl($brochureCopy)
            ->setStart(date('d.m.Y', strtotime($aInfos['visibleStart'] . ' + 1 day')))
            ->setEnd($aInfos['validEnd'])
            ->setVisibleStart($aInfos['visibleStart'])
            ->setOptions('no_cut')
            ->setDistribution($key);

        if (preg_match('#_V$#', $suffix)) {
            $eBrochure->setBrochureNumber(substr($key, -13, 13) . '_KW' . $kw . '_' . $yr . $suffix)
                ->setTitle('Vorschau: ' . $companyPrefix . 'Wochenangebote')
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 4 days')))
                ->setVisibleEnd(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 2 days')))
                ->setEnd(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 2 days')))
                ->setStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 4 days')));
        }

        return $eBrochure;
    }

    private function setClickouts($localPath): string
    {
        $clickoutFile = APPLICATION_PATH . '/../public/files/tmp/clickoutEdekaNord.csv';
        $pageDimensions = $this->sPdf->getAnnotationInfos($localPath);

        $aCoordsToLink = [
            [
                'page' => 0,
                'height' => $pageDimensions[0]->height,
                'width' => $pageDimensions[0]->width,
                'startX' => $pageDimensions[0]->width - 35,
                'endX' => $pageDimensions[0]->width - 25,
                'startY' => $pageDimensions[0]->height - 350,
                'endY' => $pageDimensions[0]->height - 360,
                'link' => 'https://edeka-nord.hand-zettel.de/EDEKAWHH?utm_source=offerista&utm_medium=eflyer&utm_campaign=iHZ&utm_id=30#page=2'
            ],
            [
                'page' => 0,
                'height' => $pageDimensions[0]->height,
                'width' => $pageDimensions[0]->width,
                'startX' => $pageDimensions[0]->width - 35,
                'endX' => $pageDimensions[0]->width - 25,
                'startY' => $pageDimensions[0]->height - 850,
                'endY' => $pageDimensions[0]->height - 860,
                'link' => 'https://edeka-nord.hand-zettel.de/EDEKAWHH?utm_source=offerista&utm_medium=eflyer&utm_campaign=iHZ&utm_id=30#page=2'
            ],
            [
                'page' => 0,
                'height' => $pageDimensions[0]->height,
                'width' => $pageDimensions[0]->width,
                'startX' => 300,
                'endX' => 310,
                'startY' => $pageDimensions[0]->height - 350,
                'endY' => $pageDimensions[0]->height - 360,
                'link' => 'https://edeka-nord.hand-zettel.de/EDEKAWHH?utm_source=offerista&utm_medium=eflyer&utm_campaign=iHZ&utm_id=30#page=2'
            ],
        ];

        $fh = fopen($clickoutFile, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $this->sPdf->setAnnotations($localPath, $clickoutFile);
    }

    private function setGenericClickout($localPath): string
    {
        $iPages = count(json_decode($this->sPdf->extractText($localPath)));
        $clickoutFile = APPLICATION_PATH . '/../public/files/tmp/clickoutEdekaNord.csv';
        $pageDimensions = $this->sPdf->getAnnotationInfos($localPath);

        $aCoordsToLink = [
            [
                'page' => $iPages - 1,
                'height' => $pageDimensions[$iPages - 1]->height,
                'width' => $pageDimensions[$iPages - 1]->width,
                'startX' => $pageDimensions[$iPages - 1]->width - 35,
                'endX' => $pageDimensions[$iPages - 1]->width - 25,
                'startY' => 35,
                'endY' => 25,
                'link' => 'https://www.edeka.de/nord/gewinnspiel/teilnahmebedingungen-nord-newsletter-gewinnspiel.jsp?at_medium=display&at_campaign=NLL&at_variant=LinkAd&at_channel=DPP'
            ],
            [
                'page' => $iPages - 1,
                'height' => $pageDimensions[$iPages - 1]->height,
                'width' => $pageDimensions[$iPages - 1]->width,
                'startX' => $pageDimensions[$iPages - 1]->width - 80,
                'endX' => $pageDimensions[$iPages - 1]->width - 70,
                'startY' => 180,
                'endY' => 190,
                'link' => 'https://www.edeka.de/newsletter/anmelden.jsp?at_medium=display&at_campaign=NLL&at_variant=LinkAd&at_channel=DPP'
            ]
        ];
        $fh = fopen($clickoutFile, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $this->sPdf->setAnnotations($localPath, $clickoutFile);
    }

}
