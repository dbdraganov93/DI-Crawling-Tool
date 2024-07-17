<?php
/**
 * Store Crawler for EDEKA Minden (IDs: 73682-73686)
 */

class Crawler_Company_EdekaMinden_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aConfig = new Zend_Config_Ini(APPLICATION_PATH . '/modules/Crawler/companies/Edeka/distributions.ini', 'production');

        $localPath = $sFtp->connect('2', TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#PLZ_WhatsApp\.xlsx$#', $singleRemoteFile)) {
                $localStorePath = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localStorePath, TRUE)->getElement(7)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!preg_match('#' . $aConfig->{$companyId}->regionName . '#i', $singleRow['REGION_KÜRZEL'])
                || !preg_match('#' . $aConfig->{$companyId}->vertriebsschieneName . '#i', $singleRow['VERTRIEBSSCHIENE'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['MARKT_ID'])
                ->setTitle($singleRow['BEZEICHNUNG'])
                ->setStreetAndStreetNumber($singleRow['STRAßE'])
                ->setZipcode(str_pad($singleRow['PLZ'], 5, '0', STR_PAD_LEFT))
                ->setCity($singleRow['ORT'])
                ->setPhoneNormalized($singleRow['TELEFON'])
                ->setFaxNormalized($singleRow['FAX'])
                ->setEmail($singleRow['EMAIL'])
                ->setStoreHoursNormalized($singleRow['STANDARD_ÖFFNUNGSZEITEN'])
                ->setDistribution($singleRow['WERBEGEBIET_HZ'])
                ->setWebsite(preg_replace('#index\.html#', 'blaetterkatalog/pdf/complete.pdf', $singleRow['URL_HZ']));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId, 2, FALSE);
    }
}
