<?php

/*
 * Store Crawler für AC Auto Check (ID: 28939)
 */

class Crawler_Company_AcAutoCheck_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sMjFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sMjFtp->connect('28939');

        $aXlsName = array(
            '28939' => 'ac_auto_check.xlsx',
            '72092' => 'meisterhaft.xlsx',
            '72042' => 'autoPARTNER.xlsx',
        );

        $cStores = new Marktjagd_Collection_Api_Store();

        $fileName = $sMjFtp->downloadFtpToCompanyDir($aXlsName[$companyId], $companyId);
        if (!$fileName) {
            throw new Exception($companyId . ': could not download excel file');
        }

        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sPhpExcel->readFile($fileName, true);
        $aData = $worksheet->getElement(0)->getData();

        foreach ($aData as $dataElement) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($dataElement['Firma']);
            $eStore->setStreetAndStreetNumber($dataElement['Anschrift']);
            $eStore->setZipcode($dataElement['PLZ']);
            $eStore->setCity($dataElement['Ort']);
            $eStore->setPhoneNormalized($dataElement['Telefon']);
            $eStore->setFaxNormalized($dataElement['Fax']);
            $eStore->setWebsite($dataElement['www']);

            $sOpening = '';
            if (strlen($dataElement['Öffnungszeiten Mo - Fr']) && !preg_match('#[A-Z][a-z]#', $dataElement['Öffnungszeiten Mo - Fr'])) {
                $sOpening .= 'Mo-Fr ' . $dataElement['Öffnungszeiten Mo - Fr'];
            } elseif (strlen($dataElement['Öffnungszeiten Mo - Fr']) && preg_match('#[A-Z][a-z]#', $dataElement['Öffnungszeiten Mo - Fr'])) {
                $sOpening .= $dataElement['Öffnungszeiten Mo - Fr'];
            }

            if (strlen($dataElement['Öffnungszeiten Sa'])) {
                if (strlen($sOpening)) {
                    $sOpening .= ', ';
                }

                $sOpening .= 'Sa ' . $dataElement['Öffnungszeiten Sa'];
            }

            $eStore->setStoreHoursNormalized($sOpening);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
