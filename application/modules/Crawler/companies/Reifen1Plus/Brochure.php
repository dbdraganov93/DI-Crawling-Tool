<?php
/**
 * Store Crawler für Reifen 1+ (ID: 72353)
 */

class Crawler_Company_Reifen1Plus_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);

        $aBrochureParts = [];
        $brochureAssignments = null;
        $localPath = APPLICATION_PATH . '/../public/files/tmp/';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#^.+?\.pdf$#', $singleFile)) {
                $aBrochureParts[$singleFile] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (preg_match('#^.+?processed\.csv#', $singleFile)) {
                $brochureAssignments = $sPss->readFile($sFtp->downloadFtpToDir($singleFile, $localPath), true)->getElement(0)->getData();
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureAssignments as $brochureAssignment) {
            if ($brochureAssignment['Standort-ID'] == null) {
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($brochureAssignment['Anzeigename'])
                ->setStoreNumber($brochureAssignment['Standort-ID'])
                ->setVisibleStart(date('d.m.y', DateTime::createFromFormat('!d.m.y', $brochureAssignment['angezeigt von'])->getTimestamp()))
                ->setVisibleEnd(date('d.m.y', DateTime::createFromFormat('!d.m.y', $brochureAssignment['angezeigt bis'])->getTimestamp()))
                ->setStart(date('d.m.y', DateTime::createFromFormat('!d.m.y', $brochureAssignment['angezeigt von'])->getTimestamp()));
//                ->setEnd(date('d.m.y', DateTime::createFromFormat('!d.m.y', $brochureAssignment['angezeigt bis'])->getTimestamp()));

            $aFiles = [$aBrochureParts[$brochureAssignment['Basis-PDF']], $aBrochureParts[$brochureAssignment['Händler-PDF']]];
            $brochure = $sPdf->merge($aFiles, $localPath);

            $pageCount = $sPdf->getPageCount($brochure);
            $clickoutLink = $brochureAssignment['Clickout URL (optional)'];

            $aData = [];
            for ($i = 0;$i < $pageCount; $i++) {
                $aData[] = [
                    'page' => $i,
                    'link' => $clickoutLink,
                    'startX' => '340',
                    'endX' => '390',
                    'startY' => '740',
                    'endY' => '790'
                ];
            }

            $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $eBrochure->getHash() . '.json';

            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($aData));
            fclose($fh);

            $brochure = $sPdf->setAnnotations($brochure, $coordFileName);
            $eBrochure->setUrl($brochure);
            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
