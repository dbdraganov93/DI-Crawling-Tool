<?php

/**
 * Store Crawler für McCain (ID: 81373)
 */
class Crawler_Company_McCain_Store extends Crawler_Generic_Company
{

    /*
     * The Excel file Geo_Store-Liste contains some format errors and PLZ lines the character
     * "=" needs to be find/replaced
     */
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $localPath = $sFtp->connect($companyId, true);

        $xlsFile = null;
        foreach ($sFtp->listFiles() as $ftpFile) {
            if(!preg_match('#Geo_Store-Liste#', $ftpFile)) {
                continue;
            }

            $xlsFile = $sFtp->downloadFtpToDir($ftpFile, $localPath);
         }

        if(empty($xlsFile)) {
            throw new Exception('No Geo_Store-Liste.xlsx was found on FTP!');
        }

        $aData = [];
        for ($i = 0; $i <= 3; $i++) {
            $aData[] = $sExcel->readFile($xlsFile, true)->getElement($i)->getData();
        }

        $allStoresData = [];
        $salesRegions = [];
        foreach($aData as $xlsTab => $xlsTabContent) {
            $tabName = '';

            switch ($xlsTab) {
                case 0:
                    $tabName = "Warenverfügbarkeit Smoked BBQ";
                    break;
                case 1:
                    $tabName = "Warenverfügbarkeit Cheese&Bacon";
                    break;
                case 2:
                    $tabName = "Warenverfügbarkeit Veggie Chill";
                    break;
                case 3:
                    $tabName = "Warenverfügbarkeit Pulled Pork";
                    break;
            }

            foreach ($xlsTabContent as $xlsContent) {
                // this will filter out the hidden xls lines
                if(empty($xlsContent['PLZ'])) {
                    continue;
                }

                $key = $xlsContent['Markt'] . $xlsContent['Straße '];

                if(array_key_exists($key, $salesRegions)) {
                    array_push($salesRegions[$key], $tabName);
                } else {
                    $salesRegions[$key] = [$tabName];
                }

                if(array_key_exists($key, $allStoresData)) {
                    continue;
                }

                $allStoresData[$key] = $xlsContent;
            }
        }

        foreach ($allStoresData as $storeData) {
            $eStore = new Marktjagd_Entity_Api_Store;
            $eStore->setTitle($storeData['Markt'])
                ->setStoreNumber(md5($storeData['Markt'] . $storeData['Straße ']))
                ->setDistribution(implode(',', $salesRegions[$storeData['Markt'] . $storeData['Straße ']]))
                ->setStreetAndStreetNumber($storeData['Straße '])
                ->setZipcode(str_replace('"', '', $storeData['PLZ']))
                ->setCity($storeData['Stadt'])
            ;

            $cStores->addElement($eStore);
        }


        return $this->getResponse($cStores);
    }
}
