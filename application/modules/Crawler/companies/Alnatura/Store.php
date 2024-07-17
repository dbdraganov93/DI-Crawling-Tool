<?php

/*
 * Store Crawler für Alnatura (ID: 22232)
 */

class Crawler_Company_Alnatura_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.alnatura.de/';
        $searchUrl = $baseUrl . 'api/sitecore/stores/FindStoresforMap?ElementsPerPage=10000&lat=50&lng=10&radius=1000&Tradepartner=Alnatura%20Super%20Natur%20Markt';
        $detailUrl = $baseUrl . 'api/sitecore/stores/StoreDetails?storeid=';
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

        $aData = $sPss->readFile($localSpecialFile, TRUE)->getElement(0)->getData();
        foreach ($aData as $singleRow) {
            $aSpecial[] = $singleRow['PLZ'];
        }

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $page = curl_exec($ch);
        curl_close($ch);
        $jStores = json_decode($page)->Payload;

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $ch = curl_init($detailUrl . $singleJStore->Id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $page = curl_exec($ch);
            curl_close($ch);
            $jDetailStore = json_decode($page)->Payload;

            $pattern = '#Deutschland#';
            if (!preg_match($pattern, $jDetailStore->Country)) {
                $this->_logger->info($companyId . ': not a german store ' . $singleJStore->itemid);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($jDetailStore->StoreId)
                ->setStreetAndStreetNumber($jDetailStore->Street)
                ->setZipcode($jDetailStore->PostalCode)
                ->setCity($jDetailStore->City)
                ->setStoreHoursNormalized($jDetailStore->OpeningTime)
                ->setPhoneNormalized($jDetailStore->Tel)
                ->setWebsite($baseUrl . $jDetailStore->StoreDetailPage);

            if (in_array($eStore->getZipcode(), $aSpecial)) {
                $eStore->setDistribution('Lavera');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

}
