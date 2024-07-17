<?php
/**
 * Store Crawler für Billa Plus AT (ID: 73375)
 */

class Crawler_Company_BillaPlusAt_Store extends Crawler_Generic_Company
{
    private $aCounties;

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect('dataAt');
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localAssignmentFile = $sFtp->downloadFtpToDir('/dataAt/at_counties.csv', $localPath);
        $sFtp->close();

        if ($localAssignmentFile == false) {
            throw new Exception('unable to download file with counties and corresponding plz from ftp');
        }

        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aData = $sPhpSpreadsheet->readFile($localAssignmentFile, TRUE, ';')->getElement(0)->getData();
        $this->aCounties = [];
        foreach ($aData as $singleColumn) {
            $this->aCounties[$singleColumn['PLZ']] = $singleColumn['Bundesland'];
        }

        $sPage = new Marktjagd_Service_Input_Page();
        $storeFeedUrl = 'https://shop.billa.at/api/storefeed';

        $sPage->open($storeFeedUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if ($singleJStore->subBrand == 'BillaPlus') {
                $eStore = new Marktjagd_Entity_Api_Store();

                $strTimes = '';
                foreach ($singleJStore->openingTimesStructured as $aDays) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= $aDays->dayOfWeek . ' ' . $aDays->opens . '-' . $aDays->closes;
                }

                if ($singleJStore->hasParking) {
                    $eStore->setParking('vorhanden');
                }

                $eStore->setStoreNumber($singleJStore->storeId)
                    ->setLongitude($singleJStore->longitude)
                    ->setLatitude($singleJStore->latitude)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setDistribution($this->getDistribution($singleJStore))
                    ->setPhoneNormalized($singleJStore->telephoneAreaCode . $singleJStore->telephoneNumber)
                    ->setStoreHoursNormalized($strTimes);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function getDistribution($singleJStore): ?string
    {
        $distributions = [
            'B' => 'Burgenland',
            'K' => 'Kärnten',
            'N' => 'Niederösterreich',
            'O' => 'Oberösterreich',
            'Sa' => 'Salzburg',
            'St' => 'Steiermark',
            'T' => 'Tirol',
            'V' => 'Vorarlberg',
            'W' => 'Wien',
        ];

        if (array_key_exists($singleJStore->zip, $this->aCounties)) {
            if (array_key_exists($this->aCounties[$singleJStore->zip], $distributions)) {
                return $distributions[$this->aCounties[$singleJStore->zip]];
            }
        }
        return null;
    }
}
