<?php

/*
 * Store Crawler für Edeka NST (IDs: 69469 - 69474)
 */

class Crawler_Company_EdekaNST_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sEmail = new Marktjagd_Service_Transfer_Email('EdekaNST');

        $week = 'next';
        $weekNo = date('W', strtotime($week . ' week'));

        $aDistributions = [
            69469 => 'E\s*center',
            69470 => '(EDEKA|Sonstige|E aktiv markt|Frischemarkt)',
            69471 => 'nah & gut',
            69472 => 'Marktkauf',
            69473 => 'diska',
            69474 => 'Kupsch',
            73726 => 'Naturkind'
        ];

        $cEmails = $sEmail->generateEmailCollection(69470);
        foreach ($cEmails->getElements() as $eEmail) {
            if (!preg_match('#KW' . $weekNo . '#', $eEmail->getSubject())) {
                continue;
            }
            foreach ($eEmail->getLocalAttachmentPath() as $singleAttachment) {
                if (preg_match('#markets#i', $singleAttachment)) {
                    $localStoreFile = $singleAttachment;
                } elseif (preg_match('#online\s*verteilung#i', $singleAttachment)) {
                    $localAssignmentFile = $singleAttachment;
                }
            }

            $aData = $sPss->readFile($localAssignmentFile)->getElement(0)->getData();

            $aRadii = [];
            foreach ($aData as $singleRow) {
                if (!is_int($singleRow[8])) {
                    continue;
                }
                $aRadii[$singleRow[1]] = $singleRow[8];
            }

            $aInfos = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

            $cStores = new Marktjagd_Collection_Api_Store();
            foreach ($aInfos as $singleStore) {
                if (!preg_match('#' . $aDistributions[$companyId] . '#i', $singleStore['VERTRIEBSSCHIENE'])) {
                    continue;
                }
                $strSalesRegion = $singleStore['WERBEGEBIET_HZ'];

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
                    ->setWebsite($singleStore['URL_EDEKA'])
                    ->setDefaultRadius($aRadii[$eStore->getStoreNumber()]);

                if (preg_match('#Trabold#i', $eStore->getTitle())) {
                    $eStore->setTitle(preg_replace('#EDEKA\s*Frischecenter\s*#', 'E center', $eStore->getTitle()));
                }

                if ($eStore->getStoreNumber() == '10007133') {
                    $eStore->setCity('Neumarkt in der Oberpfalz')
                        ->setStreet('Hans-Dehn-Straße')
                        ->setLatitude(49.2740549)
                        ->setLongitude(11.4510461)
                        ->setDefaultRadius(10);
                }

                $eStore->setDistribution($strSalesRegion);

                $cStores->addElement($eStore);
            }

        }

        return $this->getResponse($cStores);
    }
}