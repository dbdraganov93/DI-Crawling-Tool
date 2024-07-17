<?php

/**
 * Storecrawler für A.T.U. (ID: 83)
 *
 */
class Crawler_Company_ATU_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        {
            $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
            $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

            $localPath = $sFtp->connect($companyId);

            foreach ($sFtp->listFiles() as $singleFile) {
                if (preg_match('#\.xlsx?$#', $singleFile)) {
                    $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    break;
                }
            }
            $sFtp->close();

            $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

            $cStores = new Marktjagd_Collection_Api_Store();
            foreach ($aData as $singleStore) {
                $strTimes = '';
                foreach ($singleStore as $key => $value) {
                    if (preg_match('#Öffnungszeiten\s*(.+)#', $key, $dayMatch)) {
                        if (strlen($strTimes)) {
                            $strTimes .= ', ';
                        }
                        $strTimes .= $dayMatch[1] . ' ' . $value;
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleStore['Nummer der Filiale'])
                    ->setTitle($singleStore['Bezeichnung'])
                    ->setCity($singleStore['Stadt'])
                    ->setStreetAndStreetNumber($singleStore['Straße'])
                    ->setZipcode($singleStore['Postleitzahl'])
                    ->setPhone(preg_replace('#/#', '', $singleStore['Telefonnummer']))
                    ->setEmail($singleStore['E-Mail'])
                    ->setWebsite($singleStore['Link auf die Filialseite'])
                    ->setImage($singleStore['Link auf Bild der Filiale'])
                    ->setLatitude($singleStore['Breitengrad'] / 100000)
                    ->setLongitude($singleStore['Längengrad'] / 100000)
                    ->setStoreHoursNormalized($strTimes);

                $cStores->addElement($eStore);
            }
            return $this->getResponse($cStores, $companyId);
        }
    }
}