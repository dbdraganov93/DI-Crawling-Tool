<?php

/**
 * Discover Brochure Hofmeister (ID: 69717)
 */
class Crawler_Company_Hofmeister_NormalBrochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        //TODO This is just a test crawler and should be changed later on after FTP is set up
        /** This is just to set MAFOs and Zips - Just change the fields "//change here"
         *  It gets the already linked brochures and add ZIPS and Mafos
         *  At the moment we have to run 1 time for each brochure ache change data manually
         */

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $localPath = $sFtp->connect($companyId, true);

        $sFtp->changedir('temp');

        $mafoPdf = null;
        $zipExcelFile = null;
        $brochures = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if(preg_match('#Borst Mafo#', $singleFile)){
                //$mafoPdf = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if(preg_match('#Hofmeister Mafo#', $singleFile)){
                $mafoPdf = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if(preg_match('#plz21\.xlsx$#', $singleFile)){
                $zipExcelFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if (!preg_match('#\.pdf$#', $singleFile)) {
                continue;
            }
            $fileName = substr($singleFile, 0,-4);
            $brochures[$fileName] = $fileName;
        }

        $sFtp->changedir('Roman'); // folder Roman created for us to get the linked brochures

        $brochureName = 'K_11_21_Uhrzeit'; //change here

        $normalBrochure = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            //var_dump($singleFile);
            if(preg_match('#' . $brochureName . '.pdf#', $singleFile)){
                $pdf = $sFtp->downloadFtpToDir($singleFile, $localPath);

                $normalBrochure[substr($singleFile, 0,-4)] = $pdf;
                continue;
            }

        }

        if(empty($zipExcelFile)) {
            throw new Exception('No Excel PLZ file found');
        }

        // get Excel tabs
        $aDataBietigheim = $sExcel->readFile($zipExcelFile)->getElement(0)->getData();
        $aDataSindelfingen = $sExcel->readFile($zipExcelFile)->getElement(1)->getData();
        $aDataMoebel = $sExcel->readFile($zipExcelFile)->getElement(2)->getData();
        $aDataHeilbronn = $sExcel->readFile($zipExcelFile)->getElement(3)->getData();
        $aDataPforzheim = $sExcel->readFile($zipExcelFile)->getElement(4)->getData();
        $aDataBk = $sExcel->readFile($zipExcelFile)->getElement(5)->getData();
        $aDataRe = $sExcel->readFile($zipExcelFile)->getElement(6)->getData();
        $aDataEsslingen = $sExcel->readFile($zipExcelFile)->getElement(8)->getData(); //error
        $aDataUlm = $sExcel->readFile($zipExcelFile)->getElement(9)->getData(); //error
        $aDataGop = $sExcel->readFile($zipExcelFile)->getElement(10)->getData();

        //var_dump($aDataGop);
        //die;

        $zipsBietigheim = $this->getZipsFromExcel($aDataBietigheim);
        $zipsSindelfingen = $this->getZipsFromExcel($aDataSindelfingen);
        $zipsMoebel = $this->getZipsFromExcel($aDataMoebel);
        $zipsHeilbronn = $this->getZipsFromExcel($aDataHeilbronn);
        $zipPforzheim = $this->getZipsFromExcel($aDataPforzheim);
        $zipBk = $this->getZipsFromExcel($aDataBk);
        $zipRe = $this->getZipsFromExcel($aDataRe);
        //Stuttgard
        $zipEsslingen = $this->getZipsFromExcel($aDataEsslingen);
        $zipsUlm = $this->getZipsFromExcel($aDataUlm);
        $zipsGop = $this->getZipsFromExcel($aDataGop);

        $coordFiles = [];
        foreach ($normalBrochure as $key => $brochure) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setStoreNumber('2') //change here
                ->setUrl($sPdf->insert($brochure, $mafoPdf, 3))
                ->setVariety('leaflet')
                ->setStart('17.11.2021')
                ->setVisibleStart('17.11.2021')
                ->setEnd('18.01.2022')
                ->setTitle('Hofmeister: Küchenspezial')
                ->setBrochureNumber($brochureName . '_mafo')
                ->setZipCode($zipsSindelfingen) //change here
            ;

            $cBrochures->addElement($eBrochure);

            $this->_logger->info('Added brochure -> ' . $key);

            $sFtp->changedir('..');
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getZipsFromExcel($excelData): string
    {
        $zips = [];
        foreach ($excelData as $data) {
            if(empty($data[0]) || $data[0] == 'PLZ') {
                continue;
            }

            $zips[] = (string) $data[0];
        }

        return implode(',', $zips);
    }
}
