<?php

/**
 * Brochure-Crawler für Edeka Czaikowski (ID: 71876)
 */
class Crawler_Company_EdekaCzaikowski_Brochure extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {        
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aDists = $sApi->findDistributionsByCompany($companyId);                
        
        $sFtp->connect($companyId);
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);                
                        
        if (!$aFiles = $sFtp->listFiles()) {
            throw new Exception($companyId . ': no brochures available.');
        }
        
        foreach ($aFiles as $sFile) {                     
            if (preg_match('#KW0?' . $sTimes->getWeekNr('next') . '#', $sFile)) {
                $localFileNames[] = $sFtp->downloadFtpToDir($sFile, $localDirectory);            
            }
        }
               
        if (!$localFileNames){
            throw new Exception($companyId . ': no brochures available.');
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($localFileNames as $slocalFile) {                                              
            if (!preg_match('#Czaikowski_(.+?)_?KW(.+?)\.pdf#i', $slocalFile, $fileStores)){
                throw new Exception('invalid file found: ' . $localFile);
            }
            
            $storeList = explode('_', $fileStores[1]);

            foreach ($storeList as $storeNum){
                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                
                $eBrochure->setUrl($ftpFolder . basename($slocalFile))
                    ->setTitle('Genuss Magazin Ausgabe ' . $fileStores[2])
                    ->setBrochureNumber('kw_' . $fileStores[2] . '_' . $sTimes->getWeeksYear() . '_store' . $storeNum)
                    ->setVariety('leaflet')                    
                    ->setVisibleStart(date('d.m.Y', strtotime('next saturday')))
                    ->setStart(date('d.m.Y', strtotime('next monday')))
                    ->setEnd(date('d.m.Y', strtotime('+5 day', strtotime($eBrochure->getStart()))))
                    ->setStoreNumber($storeNum);
                                
                $cBrochures->addElement($eBrochure, false);            
            }
        }
        
        $sFtp->transformCollection($cBrochures, '/' . $companyId . '/', 'brochures', $localDirectory);

        return $this->getResponse($cBrochures, $companyId);
    }
}
