<?php

/*
 * Store Crawler für GoodYear (ID: 71809)
 */

class Crawler_Company_Goodyear_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $localFolder = $sFtp->generateLocalDownloadFolder($companyId);
        $aLocalImageFiles = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            $pattern = '#teilnehmer\.xls#';
            if (preg_match($pattern, $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
                continue;
            }
            if (preg_match('#([^\.]+?)\.jpg#', $singleFile, $storeImageMatch)) {
                $aLocalImageFiles[$storeImageMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localFolder);
                continue;
            }
        }

        $storeDataSheet = $sExcel->readFile($localStoreFile)->getElement(0);

        $aHeader = array();
        foreach ($storeDataSheet->getData() as $singleEntry) {
            if (!strlen($singleEntry[7])) {
                continue;
            }
            if (!count($aHeader)) {
                $aHeader = $singleEntry;
                continue;
            }
            $aData[] = array_combine($aHeader, $singleEntry);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle(trim($singleStore['Firma 2']))
                    ->setStreetAndStreetNumber($singleStore['Straße'])
                    ->setZipcode($singleStore['PLZ'])
                    ->setCity($singleStore['Stadt'])
                    ->setPhoneNormalized($singleStore['Telefonnummer'])
                    ->setStoreHoursNormalized($singleStore['Öffnungszeiten'])
                    ->setStoreNumber($singleStore['2_PAYER ID'] . $singleStore['PLZ'])
                    ->setLogo(preg_replace('#(.+?)/public/(.+?)#', 'https://di-gui.marktjagd.de/$2', $aLocalImageFiles[$singleStore['Channel']]));
            
            if (!preg_match('#^https?:\/\/#', $singleStore['url'])) {
                $singleStore['url'] = preg_replace('#(.+)#', 'http://$1', $singleStore['url']);
            }
            
            $eStore->setWebsite($singleStore['url']);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
