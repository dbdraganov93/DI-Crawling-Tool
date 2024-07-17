<?php

/**
 * Store Crawler für Edeka Südwest (ID: 71668 - 71669)
 */
class Crawler_Company_EdekaSW_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sEmail = new Marktjagd_Service_Transfer_Email('EdekaSW');
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $distributions = [
            71668 => 'EDEKA',
            71669 => 'E center',
            71670 => 'Marktkauf',
            82617 => 'trinkgut'
        ];

        $cEmails = $sEmail->generateEmailCollection($companyId);
        foreach ($cEmails->getElements() as $eEmail) {
            if (!preg_match('#Marktliste#', $eEmail->getSubject())
                || !$eEmail->getLocalAttachmentPath()) {
                continue;
            }

            $localStoreFile = array_values($eEmail->getLocalAttachmentPath())[0];
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!preg_match('#' . $distributions[$companyId] . '#', $singleRow['VERTRIEBSSCHIENE'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($singleRow['BEZEICHNUNG'])
                ->setStoreNumber($singleRow['MARKT_ID'])
                ->setStreetAndStreetNumber($singleRow['STRAßE'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['ORT'])
                ->setPhoneNormalized($singleRow['TELEFON'])
                ->setEmail($singleRow['EMAIL'])
                ->setStoreHoursNormalized($singleRow['STANDARD_ÖFFNUNGSZEITEN'])
                ->setDistribution(preg_replace('#.+\/([^\/]+?)\/index.html#', '$1', $singleRow['URL_HZ']));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

}
