<?php

/*
 * Brochure Crawler für EHG Expert (ID: 71581)
 */

class Crawler_Company_Expert_BrochureWeb extends Crawler_Generic_Company
{
    private const COORDS_DOWNLOAD_URL = 'https://expert.publishing.one/%s/werbung/xml/book.xml';
    private int $multipleWeeks;
    /**
     * @var false|string
     */
    private $kwNr;
    private $localPath;
    private $brochurePath;

    /**
     * @param $companyId
     * @return Crawler_Generic_Response|void
     * @throws Exception
     *
     * This is used in conjunction with the normal Expert Brochure.
     * This only to get clickouts from their web and overwrite brochures.
     */
    public function crawl($companyId)
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        $sTransfer = new Marktjagd_Service_Transfer_Http();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $week = 'this';
        $this->kwNr = $sTimes->getWeekNr($week);

        $this->_logger->info('Getting FTP data');
        $this->localPath = $sFtp->connect($companyId, TRUE);

        $distributionExcelFilePath = '';
        foreach ($sFtp->listFiles('.', '#expert-PLZ\s*Liste-final-Stand\s*112023\.xlsx#') as $singleFile) {
            $distributionExcelFilePath = $sFtp->downloadFtpToDir($singleFile, $this->localPath);
        }

        $sFtp->close();

        $aData = $sPss->readFile($distributionExcelFilePath, true)->getElement(0)->getData();
        $filteredExcelData = [];
        foreach ($aData as $data) {
            if (!$data['Standort ID']) {
                continue;
            }
            $filteredExcelData[$data['Standort ID']][] = $data['PLZ'];
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($filteredExcelData as $storeNumber => $zipcodeData) {
            $xmlPath = $sTransfer->getRemoteFile(
                sprintf(self::COORDS_DOWNLOAD_URL, $storeNumber),
                $this->localPath
            );
            $coords = $this->getCoordsFromXml($xmlPath, $storeNumber);

            if (!$coords) {
                continue;
            }

            $localBrochurePath = $sTransfer->getRemoteFile($this->brochurePath, $this->localPath);
            $linkedBrochure = $sPdf->setAnnotations($localBrochurePath, $coords);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($linkedBrochure)
                ->setTitle('Expert: Wochenangebote')
                ->setBrochureNumber($this->kwNr . '_' . date('Y', strtotime($week . ' week wednesday')) . '_' . $storeNumber)
                ->setStart(date('d.m.Y', strtotime($week . ' week wednesday')))
                ->setEnd(date('d.m.Y', strtotime($week . ' week saturday')))
                ->setVisibleStart($eBrochure->getStart())
                ->setZipCode(implode(',', $zipcodeData));

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }

    private function getCoordsFromXml(string $xmlPath, string $webId): string
    {
        $xmlDataObject = simplexml_load_file($xmlPath);
        if (!$xmlDataObject) {
            return '';
        }
        $this->brochurePath = 'https://expert.publishing.one/' . $webId . '/werbung/' . (string)$xmlDataObject->menus->downloads->submenu->entry->attributes()->url[0];

        $pdfSize = ['width' => 0, 'height' => 0];
        foreach ($xmlDataObject->resolutions->resolution as $resolution) {
            if ((string)$resolution->attributes()->path != 'preview/small/') {
                continue;
            }

            $pdfSize['width'] = (string)$resolution->attributes()->width;
            $pdfSize['height'] = (string)$resolution->attributes()->height;
        }

        $aCoordsToLink = [];
        foreach ($xmlDataObject->pages->page as $page) {
            $pageNumber = $page['pagenumber'];
            foreach ($page->hotspots->hotspot as $clickout) {
                $aCoords = explode(',', (string)$clickout->attributes()->coords);

                $aCoordsToLink[] = [
                    # for pdfbox page nr is 0-based
                    'page' => (int)$pageNumber - 1,
                    'height' => $pdfSize['height'],
                    'width' => $pdfSize['width'],
                    'startX' => $aCoords[0],
                    'endX' => $aCoords[2] + $aCoords[0],
                    'startY' => $pdfSize['height'] - $aCoords[1], // inverted Y position
                    'endY' => $pdfSize['height'] - $aCoords[1] - $aCoords[3],
                    'link' => 'https://www.expert.de/suche?q=' . $clickout->attributes()->params . '&branch_id=' . $webId
                ];
            }
        }

        $coordFileName = $this->localPath . 'coordinates_87.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $coordFileName;
    }

}
