<?php

/**
 * Brochure crawler for Hellweg DE, AT and BayWa (ID: 28323, 72463, 69602)
 */
class Crawler_Company_Hellweg_BrochureGA extends Crawler_Generic_Company
{
    private string $_companyId;
    private Marktjagd_Service_Output_Pdf $_sPdf;
    /**
     * @var bool|string
     */
    private string $_localPath;

    public function crawl($companyId)
    {
        $this->_companyId = $companyId;
        $week = 'next';
        $weekNo = date('W', strtotime($week . ' week'));
        $yearNo = date('Y', strtotime($week . ' week'));
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->_sPdf = new Marktjagd_Service_Output_Pdf();

        $aCompanyPattern = [
            '72127' => [
                'short' => 'GCA',
                'archiveName' => '#KW\s*' . $weekNo . '\s*GCA\.zip$#',
                'fileName' => 'GCA_([^\.]+?)',
                'title' => 'Gartencenter Augsburg',
            ]
        ];

        $this->_localPath = $sFtp->connect('28323', TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($aCompanyPattern[$this->_companyId]['archiveName'], $singleFile)) {
                $localArchive = $sFtp->downloadFtpToDir($singleFile, $this->_localPath);
            } elseif (preg_match('#PLZ-Liste_' . $aCompanyPattern[$this->_companyId]['short'] . '.xlsx#', $singleFile)) {
                $localPostalCodeFile = $sFtp->downloadFtpToDir($singleFile, $this->_localPath);
            }
        }

        $sFtp->close();
        $localBrochure = '';
        if ($sArchive->unzip($localArchive, $this->_localPath)) {
            foreach (scandir($this->_localPath) as $singleRemoteFile) {
                if (preg_match('#\.pdf$#', $singleRemoteFile)) {
                    $localBrochure = $this->_localPath . $singleRemoteFile;
                    break;
                }
            }
            if (!$localBrochure) {
                $archivePath = $this->_localPath . preg_replace('#_#', ' ', pathinfo($localArchive, PATHINFO_FILENAME));
                foreach (scandir($archivePath) as $singleFile) {
                    if (preg_match('#\.pdf$#', $singleFile, $distMatch)) {
                        $localBrochure = $archivePath . '/' . $singleFile;
                    }
                }
            }
        }

        $aPostalCodeData = $sPss->readFile($localPostalCodeFile)->getElement(0)->getData();
        $aPostalCodes = [];
        foreach ($aPostalCodeData as $singleRow) {
            if (!$singleRow[3]) {
                continue;
            }
            $aStoreNumbers = [$singleRow[3]];
            if (preg_match('#\s*;\s*#', $singleRow[3])) {
                $aStoreNumbers = preg_split('#\s*;\s*#', $singleRow[3]);
            }
            foreach ($aStoreNumbers as $singleStoreNumber) {
                if (!is_int($singleStoreNumber)) {
                    continue;
                }
                if (strlen($aPostalCodes[$singleStoreNumber])) {
                    $aPostalCodes[$singleStoreNumber] .= ',';
                }
                $aPostalCodes[$singleStoreNumber] .= $singleRow[1];
            }
        }
        ksort($aPostalCodes);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $s3Brochure = '';
        foreach ($aPostalCodes as $storeNumber => $postalCodes) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($aCompanyPattern[$companyId]['title'] . ': Wochenangebote')
                ->setUrl(strlen($s3Brochure) ? $s3Brochure : $localBrochure)
                ->setBrochureNumber('KW' . $weekNo . '_' . $yearNo . '_' . $storeNumber)
                ->setStoreNumber($storeNumber)
                ->setZipCode($postalCodes)
                ->setStart(date('Y-m-d', strtotime($week . ' week saturday')))
                ->setEnd(date('Y-m-d', strtotime($week . ' week sunday + 1 week')))
                ->setVisibleStart($eBrochure->getStart());

            $cBrochures->addElement($eBrochure, FALSE);
            if (!strlen($s3Brochure)) {
                $s3Brochure = $eBrochure->getUrl();
            }
        }

        return $this->getResponse($cBrochures);
    }

    private function _linkBrochure(string $localBrochure, array $aClickoutData): string
    {
        $aBrochureDimensions = $this->_sPdf->getAnnotationInfos($localBrochure);

        $aCoordsToLink = [];
        foreach ($aClickoutData as $singleRow) {
            $siteNo = (int)$singleRow['page'] - 1;
            $aCoordsToLink[] = [
                'page' => $siteNo,
                'height' => $aBrochureDimensions[$siteNo]->height,
                'width' => $aBrochureDimensions[$siteNo]->width,
                'startX' => ($aBrochureDimensions[$siteNo]->width * $singleRow['left']) < 10 ? 10 : $aBrochureDimensions[$siteNo]->width * $singleRow['left'],
                'endX' => $aBrochureDimensions[$siteNo]->width * ($singleRow['left'] + $singleRow['width']),
                'startY' => $aBrochureDimensions[$siteNo]->height - ($aBrochureDimensions[$siteNo]->height * $singleRow['top']),
                'endY' => $aBrochureDimensions[$siteNo]->height - ($aBrochureDimensions[$siteNo]->height * ($singleRow['top'] + $singleRow['height'])),
                'link' => $singleRow['urlAttribute'],
            ];
        }

        $coordFileName = $this->_localPath . 'coordinates_' . $this->_companyId . '_' . basename($localBrochure, '.pdf') . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $this->_sPdf->setAnnotations($localBrochure, $coordFileName);
    }
}
