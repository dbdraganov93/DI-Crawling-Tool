<?php

/**
 * Store Crawler für Rewe (ID: 23)
 */
class Crawler_Company_Rewe_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Marktliste[^\.]*\.xlsx?#', $singleFile)) {
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();

        $aStores = [];
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['WAWI_NUMMER'])
                ->setDistribution(trim(ucwords(strtolower($singleRow['REWE_REGION']))) . ',' . trim(ucwords(strtolower($singleRow['REWE_REGION']))) . '-' . trim(preg_replace('#\s+#', '', $singleRow['REWE_FORMAT'])))
                ->setStreetAndStreetNumber($singleRow['STRASSE'])
                ->setZipcode(str_pad((string)$singleRow['PLZ'], 5, '0', STR_PAD_LEFT))
                ->setCity(trim($singleRow['STADT']))
                ->setTitle('REWE Markt')
                ->setSubtitle(trim($singleRow['REWE_MARKTNAME']))
                ->setStoreHoursNormalized($singleRow['ÖFFNUNGSZEITEN_BON']);

            if ($singleRow['X_KOORDINATE'] > 0 && $singleRow['Y_KOORDINATE'] > 0) {
                $eStore->setLongitude($singleRow['X_KOORDINATE'])
                    ->setLatitude($singleRow['Y_KOORDINATE']);
            }

            $aStores[$this->_getHash($eStore)][$eStore->getDistribution()] = $eStore;

        }

        foreach ($aStores as $storeHash => $aStoresToAdd) {
            if (count($aStoresToAdd) == 1) {
                foreach ($aStoresToAdd as $singleStore) {
                    $cStores->addElement($singleStore);
                    continue 2;
                }
            }

            $storeFound = FALSE;
            foreach ($aStoresToAdd as $dist => $eStore) {
                if (preg_match('#REWE-(SM|Center)#', $dist)) {
                    $storeFound = $eStore;
                    break;
                }
            }
            if ($storeFound) {
                foreach ($aStoresToAdd as $dist => $eStore) {
                    if (!preg_match('#REWE-(SM|Center)#', $dist)) {
                        $storeFound->setDistribution($storeFound->getDistribution() . ',' . $dist);
                    }
                }
            } else {
                throw new Exception($companyId . ': no super market found.');
            }

            $cStores->addElement($storeFound, TRUE);
        }

        return $this->getResponse($cStores, $companyId);
    }

    protected function _getHash($eStore)
    {
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->normalizeStreet($eStore->getStreet());

        $hash = md5(
            strtolower($eStore->getTitle()) .
            $eStore->getZipcode() .
            $street .
            $eStore->getStreetNumber());

        return $hash;
    }
}