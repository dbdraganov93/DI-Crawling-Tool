<?php

/*
 * Prospekt Crawler für Jawoll (ID: 29087)
 */

class Crawler_Company_Jawoll_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        
        $aDists = $sApi->findDistributionsByCompany($companyId)->getElements();
        
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        
        $aFiles = array();
        foreach ($sFtp->listFiles('./Jawoll kw ' . date('W', strtotime('next week'))) as $singleFile) {
            $aFiles[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aFiles as $singleFile) {
            foreach ($aDists as $singleDist) {
                $aDistInfos = preg_split('#\s+#', $singleDist->getTitle());
                if (!preg_match('#' . $aDistInfos[0] . '#i', $singleFile)) {
                    continue;
                }
                
                $week = date('W', strtotime('next week'));
                $weekDay = substr($aDistInfos[1], 0, 2);
                
                $filePath = $sPdf->trim($singleFile);
                
                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                
                $eBrochure->setTitle('Sonderposten')
                        ->setUrl($sCsv->generatePublicBrochurePath($filePath))
                        ->setDistribution($singleDist->getTitle())
                        ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), $week, $weekDay))
                        ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), $week, 'Sa'))
                        ->setVisibleStart($eBrochure->getStart() . ' 06:00')
                        ->setVariety('leaflet')
                        ->setBrochureNumber('KW' . $week . '_' . preg_replace('#\s+#', '_', $singleDist->getTitle()));
                
                if (preg_match('#So#', $weekDay)) {
                    $eBrochure->setStart($sTimes->findDateForWeekday(date('Y', strtotime('next week')), date('W'), $weekDay))
                                ->setVisibleStart($eBrochure->getStart());
                }
                
                $cBrochures->addElement($eBrochure);
            }
        }
        
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
