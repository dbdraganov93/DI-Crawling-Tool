<?php

/*
 * Brochure Crawler für Naturgut (ID: 385)
 */

class Crawler_Company_Naturgut_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $pattern = '#kw(\d{2})\-(\d{4})[^\.]*\.pdf#i';

        foreach ($sFtp->listFiles('./') as $singleFile) {
            if (!preg_match($pattern, $singleFile, $validityMatch)) {
                continue;
            }
            $end = $sTimes->findDateForWeekday($validityMatch[2], (int)$validityMatch[1] + 1, 'Di');
            if (strtotime($end) < strtotime('now')) {
                continue;
            }

            $aFtpBrochure = $sFtp->downloadFtpToDir($singleFile, $localPath);
            $aFtpBrochure = $sPdf->exchange($aFtpBrochure);
            //$aFtpBrochure = $sPdf->separateFirstLastPage($aFtpBrochure);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('NATURGUT Bio-Angebote')
                ->setBrochureNumber('KW' . $validityMatch[1] . '-' . $validityMatch[2])
                ->setUrl($sCsv->generatePublicBrochurePath($aFtpBrochure))
                ->setStart($sTimes->findDateForWeekday($validityMatch[2], $validityMatch[1], 'Mi'))
                ->setEnd($end)
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }
        $sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

}
