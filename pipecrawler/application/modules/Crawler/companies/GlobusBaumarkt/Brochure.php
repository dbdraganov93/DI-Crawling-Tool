<?php

/*
 * Prospekt Crawler für Globus Baumarkt (ID: 22379)
 */

class Crawler_Company_GlobusBaumarkt_Brochure extends Crawler_Generic_Company

{

    private string $kw = '';
    private string $fullYear = '';

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $dateNormalizer = new Marktjagd_Service_DateNormalization_Date();

        $sFtp->connect();

        $week = 'next';
        $this->kw = date('W', strtotime($week . ' week'));
        $this->fullYear = date('Y', strtotime($week . ' week'));
        $currentKW = date('W', strtotime($week . ' week')) . '_' . date('y', strtotime($week . ' week'));

        $localPath = $sFtp->connect($companyId, true);

        $sFtp->changedir($this->fullYear . '/kw' . $this->kw);

        $citiesAndLocalBrochures = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (!preg_match('#Bfm_Kw_(' . $currentKW . ')_Reg_(?<city>[^\.]+)\.pdf#', $singleFile, $fileMatch)) {
                continue;
            }

            $this->_logger->info('Downloading brochure: ' . $singleFile);
            $city = trim(str_replace('_', ' ', $fileMatch['city']));
            $city = str_replace('oe', 'ö', $city);
            $city = str_replace('ae', 'ä', $city);
            $city = str_replace('ue', 'ü', $city);

            // Exceptions
            if ($city == 'St Wendel') {
                $city = 'Sankt Wendel';
            } elseif ($city == 'Idar Oberstein') {
                $city = 'Idar-Oberstein';
            } elseif ($city == 'Schwäbisch Hall') {
                $city = 'Schwäbisch-Hall';
            } elseif ($city == 'Leissling') {
                $city = str_replace('ss', 'ß', $city);
            }

            $citiesAndLocalBrochures[strtolower($city)] = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        $sFtp->close();

        if (empty($citiesAndLocalBrochures)) {
            throw new Exception('No brochures were found, please check the FTP folder');
        }

        $apiStores = $sApi->findStoresByCompany($companyId)->getElements();

        // this array stores all brochures for the week
        $storeNumbersWithLocalBrochures = [];

        //this array takes it to make the diff()
        $baseStores = [];

        foreach ($citiesAndLocalBrochures as $city => $localBrochure) {
            $storeFound = [];
            /** @var Marktjagd_Entity_Api_Store $apiStore */
            foreach ($apiStores as $apiStore) {
                if (preg_match('#' . $city . '#', strtolower($apiStore->getCity()))) {
                    $storeFound [] = $apiStore;
                    $storeNumbersWithLocalBrochures[$apiStore->getStoreNumber()] = $localBrochure;
                    break;
                }
            }

            foreach ($storeFound as $newStore) {
                $baseStores[] = $newStore->getStoreNumber();
            }
        }

        $allApiStores = [];
        foreach ($apiStores as $all) {
            $allApiStores[] = $all->getStoreNumber();

        }
        $brochuresStoresDifference = array_diff($allApiStores, $baseStores);

        $storesWithoutBrochure = [];
        // do the alle brochure exist?
        if (array_key_exists('alle', $citiesAndLocalBrochures)) {
            foreach ($storeNumbersWithLocalBrochures as $keyStoreNumber => $value) {
                foreach ($brochuresStoresDifference as $singleStoreNumber) {
                    if ($singleStoreNumber == (int)$keyStoreNumber) {
                        continue;
                    }

                    if (!in_array($singleStoreNumber, $storesWithoutBrochure)) {
                        $storesWithoutBrochure[] = $singleStoreNumber;
                    }

                }
            }
        }

        $storeNumbersWithLocalBrochures[implode(',', $storesWithoutBrochure)] = $citiesAndLocalBrochures['alle'];
        foreach ($storeNumbersWithLocalBrochures as $storeNumber => $localBrochure) {
            if (!$localBrochure) {
                continue;
            }
            $aPdfText = $sPdf->extractText($localBrochure);

            if (!preg_match('#(?<start>\d{2}\.\d{2})([^\d]*)(?<end>\d{2}\.\d{1,2}\.\d{2,4})#', $aPdfText, $pdfMatch)) {
                $this->_logger->alert('The crawler was unable to regex the dates. Check again the following brochure and good luck! -> ' . $localBrochure);
                continue;
            }
            if (!preg_match('#Bfm_Kw_(' . $currentKW . ')_Reg_(?<city>[^\.]+)\.pdf#', $localBrochure, $fileMatch)) {
                throw new Exception('Cannot complete the brochure number ' . $localBrochure);
            }

            $start = $dateNormalizer->normalize($pdfMatch['start'].'.');
            $end = $dateNormalizer->normalize($pdfMatch['end']);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure
                ->setTitle('Globus Baumarkt: Wochenangebote')
                ->setBrochureNumber($fileMatch['city'] . '_' . $currentKW)
                ->setUrl($this->handleClickouts($localBrochure))
                ->setStart($start)
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . '-1 day')))
                ->setEnd($end)
                ->setStoreNumber($storeNumber)
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }


    private function handleClickouts(string $localBrochure)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $annotations = $sPdf->getAnnotationInfos($localBrochure);
        $aData = [];
        $utmParameter = 'utm_source=offerista&utm_medium=prospekt&utm_campaign=prospektportale&utm_content=kw' . $this->kw . '_' . $this->fullYear;

        foreach ($annotations as $annotation) {

            if ($annotation->subtype != 'Link' || $annotation->url == NULL) {
                continue;
            }

            $aData[] = [
                'page' => $annotation->page,
                'link' => $annotation->url . '?' . $utmParameter,
                'startX' => $annotation->rectangle->startX,
                'endX' => $annotation->rectangle->endX,
                'startY' => $annotation->rectangle->startY,
                'endY' => $annotation->rectangle->endY
            ];

        }

        $coordFileName = APPLICATION_PATH . '/../public/files/click_links.json';

        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aData));
        fclose($fh);

        return $sPdf->setAnnotations($localBrochure, $coordFileName);
    }

}