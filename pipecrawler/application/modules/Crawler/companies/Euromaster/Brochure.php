<?php

/*
 * Brochure Crawler für Euromaster (ID: 28744)
 */

class Crawler_Company_Euromaster_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $cStores = $sApi->findStoresByCompany($companyId);
        $sFtp->connect($companyId);
        $localPath = '/opt/crawler/framework/public/files/ftp/28744/2017-03-13-12-00-23/';

        $localXlsFile = $sFtp->downloadFtpToDir('franchiseTeilnehmer.xls', $localPath);

        $aData = $sExcel->readFile($localXlsFile, TRUE)->getElement(0)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aData as $singleData) {
            if (!preg_match('#x#', $singleData['Teilnehmer'])) {
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            foreach ($cStores->getElements() as $eStore) {
                if (preg_match('#' . $eStore->getZipcode() . '#', $singleData['PLZ'])) {
                    $eBrochure->setStoreNumber($eStore->getStoreNumber());
                }
            }

            foreach (scandir($localPath) as $singleFile) {
                if (preg_match('#' . $singleData['KST'] . '\.pdf$#', $singleFile)) {
                    if (!preg_match('#_990\.pdf$#', $singleFile)) {
                        $aLinkedFiles = $sPdf->copyLinks($localPath . 'template_990.pdf', array($localPath . $singleFile));
                        $filePath = $aLinkedFiles[0];
                    } else {
                        $filePath = $localPath . $singleFile;
                    }
                    $eBrochure->setUrl($sFtp->generatePublicFtpUrl($filePath));
                }
            }

            $eBrochure->setStart('10.03.2017')
                        ->setEnd('31.05.2017')
                        ->setVariety('leaflet')
                        ->setTitle('Frühjahrs Angebote');

            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
