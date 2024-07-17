<?php

/**
 * Storecrawler für Mueller Drogerie(ID: 102)
 */
class Crawler_Company_Mueller_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();

        # Grundlegende URL, um alle Stores eines Landes zu erhalten
        # dieser Link würde auch mit AT, CH, ES, HR, HU und SI funktionieren
        $searchUrl = 'https://www.mueller.de/api/ccstore/allStores/?storeType=&country=DE';
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

        $aData = $sPss->readFile($localSpecialFile, TRUE)->getElement(1)->getData();
        foreach ($aData as $singleRow) {
            $aSpecial[] = $singleRow['Nr'];
        }

        $jStores = $this->curl($searchUrl);

        if (empty($jStores))
            throw new Exception('Could not get Stores from API: ' . $searchUrl);

        foreach ($jStores as $singleStore) {

            # Details wie Tel-Nr und Öffnungszeiten gibt es nur auf der Einzelseite des Stores,
            # daher hole wir uns diese Informationen ebenfalls
            $this->_logger->info('Retrieving Details für storenumber ' . $singleStore->storeNumber);
            $searchUrl = 'https://www.mueller.de/api/ccstore/byStoreNumber/' . $singleStore->storeNumber . "/";

            $storeDetails = $this->curl($searchUrl);

            if (empty($storeDetails) || $storeDetails->message == 'There is no store for this storeNumber')
                $this->_logger->warn('Could not get details for storenumber ' . $singleStore->storeNumber);

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleStore->storeNumber)
                ->setCity($singleStore->city)
                ->setTitle($singleStore->storeName)
                ->setStreetAndStreetNumber($singleStore->street)
                ->setZipcode($singleStore->zip)
                ->setSubtitle($singleStore->companyName)
                ->setPhoneNormalized($storeDetails->cCStoreDtoDetails->phone)
                ->setFaxNormalized($storeDetails->cCStoreDtoDetails->fax)
                ->setLatitude($singleStore->latitude)
                ->setLongitude($singleStore->longitude)
                ->setSection(str_replace(',', ', ', $singleStore->sections))
                ->setDistribution($singleStore->sections);

            # Öffnungszeiten müssen aus JSON geparst und in unseren String umgewandelt werden
            $cOpeningHoursJSON = $storeDetails->cCStoreDtoDetails->openingHourWeek;
            $sOpenings = '';
            foreach ($cOpeningHoursJSON as $day) {
                # Falls der Tag leer ist, oder an dem Tag nicht geöffnet ist -> überspringen
                if (!$day || !$day->open)
                    continue;

                # Komma-separierte Liste mit Öffnungszeiten erzeugen
                $sOpenings = $sOpenings == '' ? $sOpenings : $sOpenings . ' ,';
                $sOpenings = $sOpenings . $day->dayOfWeek . ' ' . $day->fromTime . '-' . $day->toTime;

            }
            $eStore->setStoreHoursNormalized($sOpenings);

            if (in_array($eStore->getStoreNumber(), $aSpecial)) {
                $eStore->setDistribution($singleStore->sections . ', Lavera');

            }

            if (!$cStores->addElement($eStore)) {
                $this->_logger->info('Failed to add ' . $singleStore->storeNumber);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function curl(string $searchUrl)
    {
        $curl = curl_init($searchUrl);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }
}
