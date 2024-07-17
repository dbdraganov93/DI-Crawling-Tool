<?php

/**
 * Store Crawler für tmp (ID: 2)
 */
class Crawler_Company_Tmp_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {

        $storesRawInfo = $this->getStoresRawInfo($companyId);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($this->getStoresInfo($storesRawInfo) as $key => $storeInfo) {

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($storeInfo['store_number'])
                ->setTitle($storeInfo['title'])
                ->setStreetAndStreetNumber($storeInfo['street'])
                ->setZipcode($storeInfo['zipcode'])
                ->setCity($storeInfo['city']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @param $companyId
     * @return array
     */
    private function getStoresRawInfo($companyId): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $locFolder = $sFtp->generateLocalDownloadFolder($companyId);
        $data = [];

        $sFtp->connect($companyId);
        foreach ($sFtp->listFiles() as $file) {
            if (preg_match("#\.xlsx?$#i", $file)) {
                $data['dataFiles'][] = $sFtp->downloadFtpToDir($file, $locFolder);
                continue;
            }
            $data[] = $sFtp->generatePublicFtpUrl($sFtp->downloadFtpToDir($file, $locFolder));
        }
        $sFtp->close();

        return $data;
    }

    /**
     * @param array $storesRawInfo
     * @return array
     */
    private function getStoresInfo(array $storesRawInfo): array
    {
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $ret = [];

        $data1 = $sExcel->readFile($storesRawInfo['dataFiles'][0], true)->getElement(0)->getData();
        $data2 = $sExcel->readFile($storesRawInfo['dataFiles'][1], true)->getElement(0)->getData();
        $data = array_merge($data1, $data2);
        foreach ($data as $datum) {
            if (!$datum["street"]) {
                continue;
            }

            $datum['logo'] = $storesRawInfo[1];
            if (preg_match('#rewe#i', $datum["title"])) {
                $datum['logo'] = $storesRawInfo[0];
            }

            $ret[] = $datum;
        }

        return $ret;
    }
}
