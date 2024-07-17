<?php

/* 
 * Brochure Crawler für OBI (ID: 74)
 */

class Crawler_Company_Obi_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $strTags = 'Garten, Grill, Pool, Beleuchtung, Markise, Wäsche, Farbe, Holz, Laminat, Bad, Werkzeug, Blumen';
        
        $sFtp->connect($companyId);
        
        $pattern = '#.*?([0-9]{4}).*?\.pdf$#';
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($sFtp->listFiles() as $singleBrochureFile) {
            if (preg_match($pattern, $singleBrochureFile, $brochureMatch)) {
                $patternXls = '#\.xls#';
                foreach($sFtp->listFiles() as $singleXlsFile) {
                    if (preg_match($patternXls, $singleXlsFile)) {
                        $localXlsFile = $sFtp->downloadFtpToCompanyDir('/' . $companyId . '/' . $singleXlsFile, $companyId);
                        $aData = $sExcel->readFile($localXlsFile, true)->getElement(0)->getData();
                        foreach ($aData as $singleStoreData) {
                            if (!preg_match('#' . $brochureMatch[1] . '#', $singleStoreData['aktion'])) {
                                continue;
                            }
                            $eBrochure = new Marktjagd_Entity_Api_Brochure();
                            $startDate = date('d.m.Y', ($singleStoreData['laufzeit_von'] - 25569) * 86400);
                            $endDate = date('d.m.Y', ($singleStoreData['laufzeit_bis'] - 25569) * 86400);
                            
                            $eBrochure->setTitle('Mach\'s einfach mit Obi')
                                    ->setStart($startDate)
                                    ->setEnd($endDate)
                                    ->setVisibleStart($eBrochure->getStart())
                                    ->setStoreNumber((string) substr($singleStoreData['markt_nummer'], - 3))
                                    ->setTags($strTags)
                                    ->setVariety('leaflet')
                                    ->setUrl($singleBrochureFile);

                            $cBrochures->addElement($eBrochure, true);
                        }
                    }
                }
            }
        }
        
        $sFtp->transformCollection($cBrochures, '/' . $companyId . '/', 'brochures', $sFtp->generateLocalDownloadFolder($companyId));
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}