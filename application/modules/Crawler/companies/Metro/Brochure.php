<?php

/*
 * Brochure Crawler für METRO (ID: 69631)
 */

class Crawler_Company_Metro_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $aPdfs = $sFtp->listFiles('.', '#METRO_#i');

        $coordFileName = $localPath . 'coordinates_' . $companyId . '.json';

        $aCoordsToLink[] = array(
            'page' => '0',
            'height' => 765.3544,
            'width' => 524.41046,
            'startX' => 372.635
            ,
            'endX' => 414.504
            ,
            'startY' => 22.5968
            ,
            'endY' => 33.4828
            ,
            'link' => 'https://www.metro.de/service/kunde-werden?cid=de:d:kw:0:off:0:0:tl:0:0'
        );

        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aPdfs as $pdf) {

            $pattern = '#(\d{2})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})#';
            if (!preg_match($pattern, $pdf, $validityMatch)) {
                throw new Exception($companyId . ': unable to get brochure validity: ' . $pdf);
            }

            $startDate = $validityMatch[1] . '.' . $validityMatch[2] . '.20' . $validityMatch[3];
            $endDate = $validityMatch[4] . '.' . $validityMatch[5] . '.20' . $validityMatch[6];

            $localBrochurePath = $sFtp->downloadFtpToDir($pdf, $localPath);
            $localBrochurePath = $sPdf->setAnnotations($localBrochurePath, $coordFileName);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($sFtp->generatePublicFtpUrl($localBrochurePath));
            $eBrochure->setStart($startDate)
                      ->setEnd($endDate)
                      ->setVisibleStart($eBrochure->getStart())
                      ->setVariety('leaflet')
                      ->setDistribution('METRO');

            if (preg_match('#METRO\_([^\_]+)\_#', $pdf, $matchTitle)) {
                $eBrochure->setTitle($matchTitle[1] . ' - Nur für Gewerbetreibende');
            }

            if (preg_match('#METRO\_([^\_]+\_\d{2}\d{2}\d{2}-\d{2}\d{2}\d{2})#', $pdf, $matchBrochureNumber)) {
                $eBrochure->setBrochureNumber(substr($matchBrochureNumber[1], 0, 25));
            }

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
