<?php

/**
 * New Store Crawler for L.Stroetmann (ID: 71734)
 */
class Crawler_Company_LStroetmann_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $spreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $stores = new Marktjagd_Collection_Api_Store();

        $storeFile = $this->downloadStoreFile($companyId);
        if (empty($storeFile)){
            throw new Exception('Company ID: ' . $companyId . ': No store file found on the FTP server!');
        }

        $storesData = $spreadsheet->readFile($storeFile, TRUE)->getElement(0)->getData();

        foreach ($storesData as $storeData) {
            $store = $this->createStore($storeData);
            $stores->addElement($store, TRUE);
        }

        return $this->getResponse($stores);
    }

    private function downloadStoreFile(int $companyId): ?string
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $storeFile = '';
        $localPath = $ftp->connect($companyId, TRUE);
        foreach ($ftp->listFiles() as $file) {
            if (preg_match('#Offerista[^.]*\.xlsx$#', $file)) {
                $storeFile = $ftp->downloadFtpToDir($file, $localPath);
                break;
            }
        }

        $ftp->close();

        return $storeFile;
    }

    private function createStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store->setStoreNumber($storeData['ZKDNR'])
            ->setTitle($storeData['ZNAM2'] ?: $storeData['ZNAM1'])
            ->setPhoneNormalized($storeData['ZTEL'])
            ->setStreetAndStreetNumber($storeData['ZSTR'])
            ->setZipcode($storeData['ZPLZ'])
            ->setCity($storeData['ZORT']);

        return $store;
    }
}
