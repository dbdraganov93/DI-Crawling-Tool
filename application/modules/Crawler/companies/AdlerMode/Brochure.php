<?php

/* 
 * Prospekt Crawler für Adler Mode (ID: 28950)
 */

class Crawler_Company_AdlerMode_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.pdf$#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        
        $localBrochurePath = $sPdf->exchange($localBrochurePath);
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setUrl($sFtp->generatePublicFtpUrl($localBrochurePath))
                ->setTitle('Wochenangebote')
                ->setStart(date('d.m.Y', strtotime('this friday')))
                ->setEnd(date('d.m.Y', strtotime('saturday +2 weeks')))
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet')
                ->setOptions('no_cut');
        
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}