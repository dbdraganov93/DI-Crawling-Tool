<?php

/**
 * Discover Brochure Hofmeister (ID: 69717)
 */
class Crawler_Company_Hofmeister_OldBrochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        //TODO This is just a test crawler and should be changed later on after FTP is set up
        /**
         * This is just to set MAFOs and Zips - Just change the fields "//change here"
         * The crawler at the moment will get all brochures from "temp" folder and Link them Poco like *
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
                //$mafoPdf = $sFtp->downloadFtpToDir($singleFile, $localPath); //change here
                continue;
            }
            if(preg_match('#Hofmeister Mafo#', $singleFile)){
                $mafoPdf = $sFtp->downloadFtpToDir($singleFile, $localPath); //change here
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

        // get Excel tabs (right now we have to do this manually, on FTP I created an example for the customer how we should proceed)
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
        foreach ($brochures as $key => $brochure) {
            $this->_logger->info('Working on the Brochure -> ' . $brochure);

            $sFtp->changedir($key . '/maps');

            // get xml pages
            foreach ($sFtp->listFiles() as $singleXML) {
                $brochures[] = $sFtp->downloadFtpToDir($singleXML, $localPath);
            }

            // get dimensions from catalog.xml
            $sFtp->changedir('../xml');
            foreach ($sFtp->listFiles() as $catalogXMLFile) {
                if(!preg_match('#catalog.xml#', $catalogXMLFile)) {
                    continue;
                }
                $catalogXML = $sFtp->downloadFtpToDir($catalogXMLFile, $localPath);
            }

            // get the complete.pdf since the original sent is doubled page
            $sFtp->changedir('..');
            foreach ($sFtp->listFiles() as $file) {
                if(!preg_match('#complete.pdf#', $file)) {
                    continue;
                }
                $completePdf = $sFtp->downloadFtpToDir($file, $localPath);
            }

            $coordFiles[$key] = $this->buildClickoutJson($brochures, $localPath, $catalogXML, $key);

            $this->_logger->info('Adding annotations to get linked pdf');
            $sPdf->setAnnotations($completePdf, $coordFiles[$key]);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setStoreNumber('1') //change here
            ->setUrl($sPdf->insert($localPath . '/complete_linked.pdf', $mafoPdf, 3))
                ->setVariety('leaflet')
                ->setStart('17.11.2021')
                ->setVisibleStart('17.11.2021')
                ->setEnd('30.11.2021')
                ->setTitle('Hofmeister: Kuschelzeit')
                ->setBrochureNumber($key . '_linked') //change here
                ->setZipCode($zipsBietigheim) //change here
            ;

            $cBrochures->addElement($eBrochure);

            $this->_logger->info('Added brochure -> ' . $key);

            $sFtp->changedir('..');
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function buildClickoutJson($xmlFiles, $localPath, $catalogXML, $brochureName)
    {
        $coordinates = [];

        $xmlString = file_get_contents($catalogXML);
        $pattern = '#<detaillevel[^>]*name="large"[^>]*width="([^"]+?)"[^>]*height="([^"]+?)"#';
        if (preg_match($pattern, $xmlString, $dimensionMatch)) {
            $pageWidth = $dimensionMatch[1];
            $pageHeight = $dimensionMatch[2];
        }

        foreach ($xmlFiles as $xmlFile) {
            if(!preg_match('#bk_(?<page>\d+)#', $xmlFile, $pageNumber)) {
                continue;
            }

            $xmlString = file_get_contents($xmlFile);
            $xmlData = new SimpleXMLElement($xmlString);

            foreach ($xmlData->area as $singleLink) {
                $coords = explode(',', (string) $singleLink->attributes()->coords);
                $link = (string) $singleLink->attributes()->id;

                $this->_logger->info('Adding annotation to ' . $link);

                $endX = min($coords[2], 1503);
                $endY = max($pageHeight - $coords[3], 0);

                $coordinates[] = [
                    # for pdfbox page nr is 0-based
                    'page' => (int) $pageNumber['page'] - 1,
                    'height' => $pageHeight,
                    'width' => $pageWidth,
                    'startX' => $coords[0] + 45.0,
                    'endX' => $endX + 45.0,
                    'startY' => $pageHeight - $coords[1] + 45.0,
                    'endY' => $endY + 45.0,
                    'link' => $link
                ];
            }
        }

        $coordFileName = $localPath . 'coordinates_' . $brochureName . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($coordinates));
        fclose($fh);

        return $coordFileName;
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
