<?php

/*
 * Store Crawler für Budni (ID: 28980)
 */

class Crawler_Company_Budni_Store extends Crawler_Generic_Company
{

    const DISTRIBUTION = '1aM-3iVH8ev4ij70wkXmXZ8RLkCrwQRH-1RRG1_DUeBA';
    protected array $distribution;

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.budni.de/';
        $searchUrl = $baseUrl . 'api/infra/branches';
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect('82365', TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.xlsx$#', $singleRemoteFile)) {
                $localSpecialFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                break;
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localSpecialFile, TRUE)->getElement(2)->getData();
        foreach ($aData as $singleRow) {
            $aSpecial[] = $singleRow['PLZ'];
        }

        $stores = $this->getStores($searchUrl);

        if (empty($stores)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        $this->readDistribution();

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($stores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $strTimes = '';

            foreach ($singleJStore->openingHours as $day => $time) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $day . ' ' . $time;
            }

            $eStore->setStoreNumber($singleJStore->id)
                ->setStreetAndStreetNumber($singleJStore->street)
                ->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city)
                ->setStoreHoursNormalized($strTimes)
                ->setLatitude($singleJStore->location->lat)
                ->setLongitude($singleJStore->location->lon)
                ->setWebsite($baseUrl)
                ->setEmail($singleJStore->email)
                ->setDistribution($this->getStoreDistribution($singleJStore->id));
//                ->setService(implode(', ', $singleJStore->branchServices));

            if (preg_match('#Parkplätze,#', $eStore->getService())) {
                $eStore->setService(preg_replace('#Parkplätze,\s+#', '', $eStore->getService()))
                    ->setParking('vorhanden');
            }

            if (in_array($eStore->getZipcode(), $aSpecial)) {
                $eStore->setDistribution('Lavera');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function getStores($searchUrl): ?array
    {
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);

        $stores = json_decode($result);

        if (empty($stores)) {
            $this->_logger->warn(
                'No stores found to download. Please, check the resource link'
            );
        }
        return $stores;
    }

    protected function readDistribution(): void
    {
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->distribution = $sGS->getFormattedInfos(self::DISTRIBUTION, 'A1', 'K');
    }

    private function getStoreDistribution(int $storeNumber): ?string
    {
        if (empty($this->distribution)) {
            return '';
        }

        $stores = array_filter($this->distribution, function($store) use ($storeNumber) {
            return (int)$store['Filiale'] == $storeNumber;
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($stores)) {
            return '';
        }

        $store = reset($stores);
        return $store['Version'];
    }
}
