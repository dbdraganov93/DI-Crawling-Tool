<?php

/**
 * Brochure Crawler für Edeka Südwest (ID: 71668, 71669, 71670, 71672, 71673 and 82617)
 */
class Crawler_Company_EdekaSW_Brochure extends Crawler_Generic_Company
{
    protected $_companyId;
    protected $_week;
    protected $_title;
    protected $_distribution;
    protected $_mafoFile;

    protected const INSERT_MAFO = false; # deactivated due to ticket #2648253345
    protected const STOP_WHATSAPP_FOR_COMPANIES = [71668, 71669, 71670, 82617];

    /**
     * @var bool|string
     */

    public function crawl($companyId)
    {
        $this->_companyId = $companyId;
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $this->_title = $sApi->findCompanyByCompanyId($companyId)["title"];

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $aStores = [];
        $this->_distribution = [];
        foreach ($cStores as $eStore) {
            if (in_array($eStore->getStoreNumber(), $aStores)) {
                continue;
            }
            $aDists = preg_split('#\s*,\s*#', preg_replace('#,WhatsApp#', '', $eStore->getDistribution()));
            foreach ($aDists as $singleDist) {
                $this->_distribution[$singleDist][] = $eStore->getStoreNumber();
            }
        }

        $this->_week = 'next';
        if (date('N') < 4) {
            $this->_week = 'this';
        }

        $localPath = $sFtp->connect(71668, TRUE);

        $aBrochuresToAssign = [];
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (!preg_match('#KW' . date('W', strtotime($this->_week . ' tuesday')) . '#', $singleFolder)) {
                continue;
            }
            foreach ($sFtp->listFiles($singleFolder) as $singleFile) {
                $pattern = '#\/KW' . date('W', strtotime($this->_week . ' tuesday')) . '_(\d{6})_(\d{6})_SUEDWEST_([^\.]+)\.pdf#';
                if (!preg_match($pattern, $singleFile, $validityMatch)) {
                    continue;
                }

                if (!array_key_exists($validityMatch[3], $this->_distribution)) {
                    continue;
                }

                $aBrochuresToAssign[$validityMatch[3]] = [
                    'filePath' => $sFtp->downloadFtpToDir($singleFile, $localPath),
                    'visibleStart' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[1]),
                    'validEnd' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[2])
                ];
            }
        }
        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $uniqueBrochure = 0;
        foreach ($aBrochuresToAssign as $distribution => $aBrochureInfos) {
            $eBrochure = $this->addBrochure($aBrochureInfos['filePath'], $distribution, $aBrochureInfos);
            $brochureCopy = preg_replace('#\.pdf#', '_2.pdf', $aBrochureInfos['filePath']);
            copy($aBrochureInfos['filePath'], $brochureCopy);

            if ($cBrochures->addElement($eBrochure)) {
                $uniqueBrochure++;
            }

            if (!in_array($companyId, self::STOP_WHATSAPP_FOR_COMPANIES)) {
                $cBrochures->addElement($this->addBrochure($brochureCopy, $distribution, $aBrochureInfos, '_WA'));
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param Marktjagd_Collection_Api_Brochure $cBrochures
     * @return Marktjagd_Collection_Api_Brochure
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception|Zend_Exception
     */
    private function addExtraLeaflets($cBrochures)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sFtp->connect(71668, TRUE);
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (preg_match('#^KW#', $singleFolder)
                || preg_match('#archive#', $singleFolder)
                || preg_match('#\.xlsx?$#', $singleFolder)) {
                continue;
            }

            $sFtp->changedir($singleFolder);
            foreach ($sFtp->listFiles() as $singleSubFolder) {
                if (preg_match('#\.xlsx?#', $singleSubFolder)) {
                    $localAssignmentFile = $sFtp->downloadFtpToDir($singleSubFolder, $localPath);
                    break;
                }
            }
            foreach ($sFtp->listFiles() as $singleSubFolder) {
                if (!preg_match('#KW\s*' . date('W', strtotime($this->_week . ' week')) . '#', $singleSubFolder)) {
                    continue;
                }

                $sFtp->changedir($singleSubFolder);
                foreach ($sFtp->listFiles() as $singleFile) {
                    $pattern = '#KW' . date('W', strtotime($this->_week . ' week')) . '_(\d{6})_(\d{6})_SUEDWEST_([^\.]+)\.pdf#';
                    if (!preg_match($pattern, $singleFile, $validityMatch)) {
                        continue;
                    }
                    $aBrochuresToAssign[$validityMatch[3]] = [
                        'filePath' => $sFtp->downloadFtpToDir($singleFile, $localPath),
                        'visibleStart' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[1]),
                        'validEnd' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[2])
                    ];
                }
            }
        }

        $sFtp->close();

        if (!$localAssignmentFile) {
            return $cBrochures;
        }

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aData = $sPss->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();

        $aExtra = [];
        foreach ($aData as $singleRow) {
            $pattern = '#\/([^\/]+?)\/index\.html#';
            if (!preg_match($pattern, $singleRow['URL_HZ'], $distMatch)
                || !in_array($distMatch[1], $this->_distribution)) {
                continue;
            }

            if (!preg_match($pattern, $singleRow['URL_UHZ'], $distExtraMatch)) {
                continue;
            }

            $aExtra[$distExtraMatch[1]][] = $distMatch[1];
        }

        foreach ($aBrochuresToAssign as $brochureKey => $aBrochureInfos) {
            if (!array_key_exists($brochureKey, $aExtra)) {
                continue;
            }

            $aBrochureInfos['filePath'] = $this->insertMafo($aBrochureInfos['filePath']);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Top Chance')
                ->setUrl($aBrochureInfos['filePath'])
                ->setStart($aBrochureInfos['visibleStart'])
                ->setEnd($aBrochureInfos['validEnd'])
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')))
                ->setVariety('leaflet')
                ->setDistribution(implode(',', $aExtra[$brochureKey]));

            $cBrochures->addElement($eBrochure);

        }

        return $cBrochures;
    }

    /**
     * @param $brochureFile
     * @return mixed
     * @throws Zend_Exception
     */
    private function insertMafo($brochureFile)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        if ($sPdf->getPageCount($brochureFile) <= 3) {
            $brochureFile = $sPdf->merge([$brochureFile, $this->_mafoFile], dirname($brochureFile) . '/');
        } else {
            $brochureFile = $sPdf->insert($brochureFile, $this->_mafoFile, 3);
        }
        return $brochureFile;
    }

    protected function addBrochure($filePath, $distribution, array $aBrochureInfos, string $prefix = ''):
    Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle($this->_title . ': Wochenangebote')
            ->setStoreNumber(implode(',', $this->_distribution[$distribution]))
            ->setUrl($filePath)
            ->setVisibleStart($aBrochureInfos['visibleStart'])
            ->setStart(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . ' + 1 day')))
            ->setBrochureNumber('KW' . date('W', strtotime($eBrochure->getStart())) . '_' . date('Y', strtotime($eBrochure->getStart())) . '_' . $distribution . $prefix)
            ->setEnd($aBrochureInfos['validEnd'])
            ->setVariety('leaflet');

        if (preg_match('#WA_#', $prefix)) {
            $eBrochure->setEnd(date('d.m.Y', strtotime($this->_week . ' week saturday')));
        }

        if (strtotime($eBrochure->getStart()) == strtotime('25.12.' . date('Y', strtotime('this year')))
            || strtotime($eBrochure->getStart()) == strtotime('26.12.' . date('Y', strtotime('this year')))) {
            $eBrochure->setStart('27.12.' . date('Y', strtotime('this year')));
        }

        return $eBrochure;
    }
}
