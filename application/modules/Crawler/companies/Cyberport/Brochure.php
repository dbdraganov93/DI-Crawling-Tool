<?php

/*
 * Brochure Crawler für Cyberport (ID: 67998)
 */

class Crawler_Company_Cyberport_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $sFtp->connect($companyId);

        $aData = array();
        foreach ($sFtp->listFiles() as $singleFolder) {
            $pattern = '#Adventskalender_Tag' . (int) date('d', strtotime('tomorrow')) . '#';
            if (preg_match($pattern, $singleFolder)) {
                $sFtp->changedir($singleFolder);
            }
            foreach ($sFtp->listFiles() as $singleFile) {
                $pattern = '#Tag' . (int) date('d', strtotime('tomorrow')) . '[^\.]*\.pdf$#';
                if (preg_match($pattern, $singleFile)) {
                    $localPdfPath = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
                    continue;
                }

                $pattern = '#Tag' . (int) date('d', strtotime('tomorrow')) . '[^\.]*\.xlsx#';
                if (preg_match($pattern, $singleFile)) {
                    $localExcelPath = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
                    continue;
                }
            }
        }

        $aLinkData = $sExcel->readFile($localExcelPath)->getElement(0)->getData();

        $aLinks = array();
        foreach ($aLinkData as $singleLinkData) {
            if (strlen($singleLinkData[2])) {
                $aLinks[] = $singleLinkData[2];
            }
        }

        $aData = array(
            array(
                'page' => '0',
                'link' => $aLinks[0],
                'startX' => '373.83',
                'endX' => '428.90',
                'startY' => '980.56',
                'endY' => '1021.22'
            ),
            array(
                'page' => '0',
                'link' => $aLinks[1],
                'startX' => '368.62',
                'endX' => '431.50',
                'startY' => '622.35',
                'endY' => '668.22'
            )
        );

        if (date('N', strtotime('tomorrow')) == 6 || date('N', strtotime('tomorrow')) == 7) {
            $aData = array(
                array(
                    'page' => '0',
                    'link' => $aLinks[0],
                    'startX' => '373.83',
                    'endX' => '428.90',
                    'startY' => '980.56',
                    'endY' => '1021.22'
                ),
                array(
                    'page' => '0',
                    'link' => $aLinks[1],
                    'startX' => '171.94',
                    'endX' => '211.38',
                    'startY' => '714.84',
                    'endY' => '752.89'
                ),
                array(
                    'page' => '0',
                    'link' => $aLinks[1],
                    'startX' => '603.09',
                    'endX' => '645.13',
                    'startY' => '714.84',
                    'endY' => '752.89'
                ),
                array(
                    'page' => '0',
                    'link' => $aLinks[1],
                    'startX' => '171.94',
                    'endX' => '211.38',
                    'startY' => '327.97',
                    'endY' => '362.12'
                ),
                array(
                    'page' => '0',
                    'link' => $aLinks[1],
                    'startX' => '603.09',
                    'endX' => '645.13',
                    'startY' => '327.97',
                    'endY' => '362.12'
                )
            );
        }

        $jsonData = json_encode($aData);
        $jsonFilePath = preg_replace('#(.+\/)([^\/].+?\.pdf)$#', '$1coordinates.json', $localPdfPath);

        $fh = fopen($jsonFilePath, 'w');
        fwrite($fh, $jsonData);
        fclose($fh);

        $pdfPath = $sPdf->setAnnotations($localPdfPath, $jsonFilePath);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Kalendertürchen ' . (int) date('d', strtotime('tomorrow')))
                ->setStart(date('d.m.Y', strtotime('tomorrow')))
                ->setEnd(date('d.m.Y', strtotime('tomorrow')))
                ->setVisibleStart('01.12.2016')
                ->setVariety('leaflet')
                ->setBrochureNumber('Weihnachtsaktion')
                ->setUrl($sCsv->generatePublicBrochurePath($pdfPath))
                ->setNational(TRUE);

        $cBrochures->addElement($eBrochure);

        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
