<?php

/**
 * Brochure-Crawler für FamilaNordost (ID: 28975, 71251)
 */
class Crawler_Company_FamilaNordost_Brochure extends Crawler_Generic_Company
{

    protected $week;
    protected const DEFAULT_FTP_FOLDER = 28975;
    protected const DEFAULT_WEEK = 'next';
    protected const PATTERN_PREFIX = [
        28975 => 'famila',
        71251 => 'Markant',
    ];

    public function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $this->week = date('W', strtotime(self::DEFAULT_WEEK . ' week'));
        $files = $this->_downloadFiles($companyId);
        $aDataToAssign = $this->_buildBrochureData($this->_getAssignmentData($files['assignmentFile']), $files['pdfs']);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aDataToAssign as $fileName => $brochureInfos) {
            $cBrochures->addElement($this->_createBrochure($fileName, $brochureInfos));
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function _downloadFiles(int $companyId): array
    {
        $files = [];
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect(self::DEFAULT_FTP_FOLDER, TRUE);
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (preg_match('#' . self::PATTERN_PREFIX[$companyId] . '_kw' . $this->week . '#i', $singleFolder)) {
                $sFtp->changedir($singleFolder);
                foreach ($sFtp->listFiles() as $singleFile) {
                    if (preg_match('#handzettel#', $singleFile)) {
                        $files['assignmentFile'] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    }
                    if (preg_match('#([^\.]+?\.pdf)#', $singleFile, $titleMatch)) {
                        $files['pdfs'][strtolower($titleMatch[1])] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    }
                }
            }
        }
        $sFtp->close();

        return $files;
    }

    private function _getAssignmentData($assignmentFile): array
    {
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $delimiter = '';
        if (preg_match('#\.csv#', $assignmentFile)) {
            $delimiter = ';';
        }
        $aData = $sPss->readFile($assignmentFile, TRUE, $delimiter)->getElement(0)->getData();


        return $aData;
    }

    private function _buildBrochureData(array $localAssignmentData, array $pdfs): array
    {
        $aDataToAssign = [];
        foreach ($localAssignmentData as $singleData) {
            $singleData['PDFName'] = (0 === substr_compare($singleData['PDFName'], '.pdf', -4))? $singleData['PDFName'] : $singleData['PDFName'] . '.pdf';
            if ($pdfs[strtolower($singleData['PDFName'])]) {
                $aDataToAssign[$pdfs[strtolower($singleData['PDFName'])]] = [
                    'brochureNumber' => str_replace('.pdf', '', $singleData['Title']) . '_KW' . $this->week,
                    'visibleStart' => $singleData['Releasedate'],
                    'validStart' => $singleData['Startdate'],
                    'validEnd' => $singleData['Enddate'],
                    'store' => preg_replace('#\.#', ',', $singleData['Storenumber'])
                ];
            }
        }

        return $aDataToAssign;
    }

    protected function _createBrochure(string $fileName, array $brochureInfos): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        return $eBrochure->setUrl($fileName)
            ->setTitle('Wochenangebote')
            ->setBrochureNumber($brochureInfos['brochureNumber'])
            ->setVisibleStart($brochureInfos['visibleStart'])
            ->setStart($brochureInfos['validStart'])
            ->setEnd($brochureInfos['validEnd'])
            ->setStoreNumber($brochureInfos['store']);
    }
}
