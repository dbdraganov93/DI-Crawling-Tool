<?php

/*
 * Brochure Crawler für Lehner Versand (ID: 72207)
 */

class Crawler_Company_LehnerCh_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $localBrochurePath = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            $localBrochurePath[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($localBrochurePath as $singleBrochurePath) {
            if (preg_match('#.*?\_([a-z]{2})\_(.*?)\.pdf#', $singleBrochurePath, $match)) {
                $localBrochurePathExchanged = $sPdf->exchange($singleBrochurePath);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle('Flyer Black Friday')
                    ->setUrl($sFtp->generatePublicFtpUrl($localBrochurePathExchanged))
                    ->setStart('23.11.2017')
                    ->setEnd('25.11.2017')
                    ->setVisibleStart('23.11.2017')
                    ->setVariety('leaflet')
                    ->setStoreNumber('id:' . $match[2])
                    ->setLanguageCode($match[1]);
                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
