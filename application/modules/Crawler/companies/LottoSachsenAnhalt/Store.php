<?php

/* 
 * Store Crawler für Lotto Sachsen Anhalt (ID: 71773)
 */

class Crawler_Company_LottoSachsenAnhalt_Store extends Crawler_Generic_Company {
    private const KW22CAMPAIGN = 'Kampagne Magdeburg/ Halle/Dessau-Roßlau';

    /**
     * @throws Exception
     */
    public function crawl($companyId) {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp    = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss    = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#st_stores\.xls#', $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#\wKW22-2021.xls$#', $singleFile)) {
                $kw22Campaign = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        if (isset($kw22Campaign)) {
            $campaignDataArray = $sPss->readFile($kw22Campaign, true)->getElement(0)->getData();
        }

        $aArticleData = $sPss->readFile($localArticleFile, true)->getElement(0)->getData();
        foreach ($aArticleData as $singleColumn) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleColumn['id'])
                ->setStreetAndStreetNumber($singleColumn['strasse'])
                ->setZipcode($singleColumn['PLZ'])
                ->setPhoneNormalized($singleColumn['Telefon'])
                ->setCity($singleColumn['Ort'])
                ->setTitle($singleColumn['name'])
                ->setLatitude((string) $singleColumn['Breitengrad'])
                ->setLongitude((string) $singleColumn['Längengrad'])
                ->setFaxNormalized($singleColumn['Fax'])
            ;

            if (isset($campaignDataArray)) {
                $keyFound = array_search($singleColumn['id'], array_column($campaignDataArray, 'id'));
                if ($keyFound) {
                    $eStore->setDistribution(self::KW22CAMPAIGN);
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId, '2', false);
    }
}
