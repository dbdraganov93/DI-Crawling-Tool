<?php

/*
 * Store Crawler für Rhein Ruhr Edeka Vertriebslinien (72178 - 72180)
 */

class Crawler_Company_EdekaRheinRuhr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $week = 'this';
        $weekNo = date('W', strtotime($week . ' week'));
        $year = date('Y', strtotime($week . ' week'));

        $aDists = [
            '22241' => 'trinkgut',
            '72178' => 'EDEKA',
            '72179' => 'Marktkauf',
            '72180' => 'E center'
        ];

        $localPath = $sFtp->connect('72178', TRUE);

        $localAssignmentFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Marktliste[^\.]+?KW' . $weekNo . '-' . $year . '\.xlsx?#', $singleFile)) {
                $this->_logger->info($companyId . ': assignment file found.');
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }

        }

        $aStoreData = $sPss->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreData as $singleStore) {
            if (!preg_match('#' . $aDists[$companyId] . '#i', $singleStore['VERTRIEBSSCHIENE'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleStore['MARKT_ID'])
                ->setTitle($singleStore['BEZEICHNUNG'])
                ->setStreetAndStreetNumber($singleStore['STRAßE'])
                ->setZipcode($singleStore['PLZ'])
                ->setCity($singleStore['ORT'])
                ->setPhoneNormalized($singleStore['TELEFON'])
                ->setFaxNormalized($singleStore['FAX'])
                ->setEmail($singleStore['EMAIL'])
                ->setService($singleStore['SERVICES'])
                ->setStoreHoursNormalized($singleStore['STANDARD_ÖFFNUNGSZEITEN'])
                ->setDistribution($singleStore['WERBEGEBIET_HZ'])
                ->setWebsite($singleStore['URL_EDEKA']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

}
