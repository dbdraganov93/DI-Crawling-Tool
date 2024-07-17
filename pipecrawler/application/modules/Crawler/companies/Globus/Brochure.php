<?php

/**
 * Brochure Crawler für Globus (ID: 422)
 */
class Crawler_Company_Globus_Brochure extends Crawler_Generic_Company
{
    private const PLZ_LIST_FILE = 'PLZ-Verteilgebiete_Gesamtübersicht.xlsx';
    private const DATE_FORMAT = 'd.m.Y';

    private int $companyId;
    private string $week;
    private string $plzFile;
    private array $assignedTitles = [];

    public function crawl($companyId)
    {
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $this->companyId = $companyId;
        $this->week = 'next';
        if ('1' === date('N')) {
            $this->week = 'this';
        }

        $brochuresToAssign = $this->findBrochures();
        if (empty($brochuresToAssign)) {
            throw new Exception('Company ID: ' . $companyId . ': No Brochures were found in FTP for KW' . date('W', strtotime($this->week . ' week')));
        }

        if (empty($this->plzFile)) {
            throw new Exception('Company ID: ' . $companyId . ': No PLZ Excel found on FTP with name: ' . self::PLZ_LIST_FILE);
        }

        $plzList = $sExcel->readFile($this->plzFile, true)->getElement(0)->getData();
        $plzListByMarket = $this->sortPlzByMarket($plzList);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochuresToAssign as $storeNumber => $aBrochures) {
            foreach ($aBrochures as $type => $brochureUrl) {
                $brochureData = $this->getBrochureData($type, $brochureUrl);
                if (empty($brochureData)) {
                    continue;
                }
                $brochure = $this->createBrochure($brochureData, $plzListByMarket, $storeNumber);
                $brochures->addElement($brochure);
            }
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function findBrochures(): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $ftp->connect($this->companyId, TRUE);
        $brochuresToAssign = [];
        foreach ($ftp->listFiles() as $ftpItem) {
            if (self::PLZ_LIST_FILE == $ftpItem) {
                $this->_logger->info('PLZ list found: ' . $ftpItem);
                $this->plzFile = $ftp->downloadFtpToDir($ftpItem, $localPath);
            }

            if (preg_match('#' . date('YW', strtotime($this->week . ' week')) . '#', $ftpItem)) {
                foreach ($ftp->listFiles($ftpItem) as $subFolder) {
                    foreach ($ftp->listFiles($subFolder) as $singleFile) {
                        if (!preg_match('#\/([a-z\-]{3})$#', $subFolder, $storeMatch)) {
                            $this->_logger->info('Company ID: ' . $this->companyId . ': not a valid name scheme: ' . $subFolder);
                            continue;
                        }
                        $path = $localPath . date('dmYHis') . '/';
                        if (!is_dir($path)) {
                            mkdir($path);
                        }
                        if (!is_dir($path . $storeMatch[1] . '/')) {
                            mkdir($path . $storeMatch[1] . '/');
                        }
                        $keyFile = '';
                        if (preg_match('#\d+_(.+)#', $ftpItem, $extraMatch)) {
                            $keyFile = preg_replace(['#_#', '#ae#'], [' ', 'ä'], $extraMatch[1]);
                        }
                        $this->_logger->info('Downloading from FTP: ' . $singleFile);
                        $brochuresToAssign[$storeMatch[1]][$keyFile] = $ftp->downloadFtpToDir($singleFile, $path . $storeMatch[1] . '/');
                    }
                }
            }
        }

        $ftp->close();

        return $brochuresToAssign;
    }

    private function sortPlzByMarket($plzList): ?array
    {
        $plzListByMarket = [];
        foreach ($plzList as $singlePlz) {
            if (empty($singlePlz['Markt ID'])) {
                continue;
            }

            $plzListByMarket[strtolower($singlePlz['Markt ID'])][] = str_pad($singlePlz['PLZ'], 5, '0', STR_PAD_LEFT);
        }

        return $plzListByMarket;
    }

    private function getBrochureData(string $type, string $brochureUrl): array
    {
        $title = 'Globus: Wochenangebote';
        $start = date(self::DATE_FORMAT, strtotime('monday ' . $this->week . ' week'));
        $end = date(self::DATE_FORMAT, strtotime('saturday ' . $this->week . ' week'));
        if (strlen($type)) {
            $title = $type == 'Globus' ? 'Globus: Wochenangebote' : 'Globus: ' . $type;
            $pattern = '#^([^\s]+)\s*#';
            if (!preg_match($pattern, $type, $typeMatch)) {
                $this->_logger->err($this->companyId . ': unknown brochure type found: ' . $type);
                return [];
            }

            if ('GlobusSpezial' === $typeMatch[1]) {
                $end = date(self::DATE_FORMAT, strtotime($start . ' + 13 days'));
            }
        }

        // If the short are "OFE" in 2 titles, create an "extra_" if titles are different
        // Globus: OnlineFaltblatt Einmachen --- Globus: OnlineFaltblatt Eisvielfalt
        $brochureNumber = null;
        $titleShort = preg_replace('#[^A-Z]#', '', $type);
        if (!array_key_exists($titleShort, $this->assignedTitles)) {
            $this->assignedTitles[$titleShort] = $title;
            $brochureNumber = $titleShort;
        } else {
            foreach ($this->assignedTitles as $key => $value) {
                if ($value === $title) {
                    $brochureNumber = $key;
                }
            }

            $extraCount = 0;
            while (empty($brochureNumber)) {
                $extraCount++;
                $suffix = 1 === $extraCount ? '' : $extraCount;
                $titleShortExtra = $titleShort . $suffix;
                if (!array_key_exists($titleShortExtra, $this->assignedTitles)) {
                    $this->assignedTitles[$titleShortExtra] = $title;
                    $brochureNumber = $titleShortExtra;
                }
            }
        }

        return [
            'number' => 'KW' . date('W', strtotime($this->week . ' week')) . '_' .
                date('Y', strtotime($this->week . ' week')) . '_' . $brochureNumber,
            'title' => $title,
            'start' => $start,
            'end' => $end,
            'url' => $brochureUrl,
        ];
    }

    private function createBrochure(array $brochureData, array $plzList, string $storeNumber): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setTitle($brochureData['title'])
            ->setUrl($brochureData['url'])
            ->setStoreNumber($storeNumber)
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart(date(self::DATE_FORMAT, strtotime($brochure->getStart() . ' - 1 day')))
            ->setBrochureNumber($brochureData['number'] . '_' . $brochure->getStoreNumber());

        if (isset($plzList[$storeNumber])) {
            $brochure->setZipCode(implode(',', $plzList[$storeNumber]));
        }

        if (preg_match('#nat#', $storeNumber)) {
            $brochure->setStoreNumber(NULL);
        }

        return $brochure;
    }
}
