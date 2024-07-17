<?php

/*
 * Prospekt Crawler für AWG Mode (ID: 84)
 */

class Crawler_Company_AWG_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aFiles = array();
        foreach ($sFtp->listFiles('.', '#\.pdf$#') as $singleBrochure) {
            $aFiles[] = $sFtp->downloadFtpToDir($singleBrochure, $localPath);
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aFiles as $singleFile) {
            $localPath = $sPdf->exchange($singleFile);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochen Angebote')
                    ->setUrl($sCsv->generatePublicBrochurePath($localPath))
                    ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $sTimes->getWeekNr(), 'Sa'))
                    ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), $sTimes->getWeekNr('next'), 'Sa'))
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }
        
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
