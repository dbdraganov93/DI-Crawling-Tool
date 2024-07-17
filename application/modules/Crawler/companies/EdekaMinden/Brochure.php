<?php

/**
 * Brochure crawler for EDEKA Minden (IDs: 73682-73686)
 */

class Crawler_Company_EdekaMinden_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfos = $sGSRead->getFormattedInfos('1e9VTKVf3eYGUKPzILf4a_abBivjRa6JY5stcEASPYT4', 'A1', 'R', 'Matching (Marktauswahl & Verteilgebiet)');
        $aPostalCodes = [];
        foreach ($aInfos as $aInfo) {
            if (!strlen($aInfo['Filnr'])) {
                continue;
            }
            $aPostalCodes[$aInfo['Filnr']] = preg_replace('#\s*\n\s*#', ',', $aInfo['PLZ (Verteilgebiete)']);
        }

        $week = 'next';
        if (date('w') != 0) {
            $week = 'this';
        }

        $aDistributions = [
            '73682' => '#EDEKA#',
            '73683' => '#Marktkauf#',
            '73684' => '#E\s*center#',
            '73685' => '#E\s+xpress#',
            '73686' => '#NP-Markt#'
        ];

        $localPath = $sFtp->connect('2', TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#WhatsApp\.xlsx$#', $singleRemoteFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localBrochurePath, TRUE)->getElement(7)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aData as $singleRow) {
            if (!preg_match('#MH#', $singleRow['REGION_KÜRZEL'])
                || !preg_match($aDistributions[$companyId], $singleRow['VERTRIEBSSCHIENE'])
                || is_null($singleRow['URL_HZ'])) {
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setBrochureNumber(date('Y_W', strtotime('monday ' . $week . ' week')) . '_' . $singleRow['FILIALNUMMER'])
                ->setTitle('Wochenangebote')
                ->setUrl(preg_replace('#index\.html#', 'blaetterkatalog/pdf/complete.pdf', $singleRow['URL_HZ']))
                ->setStoreNumber($singleRow['MARKT_ID'])
                ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')));

            if (array_key_exists($singleRow['FILIALNUMMER'], $aPostalCodes)) {
                if (date('w') != 0) {
                    $eBrochure = new Marktjagd_Entity_Api_Brochure();

                    $eBrochure->setBrochureNumber(date('Y_W', strtotime('monday next week')) . '_' . $singleRow['FILIALNUMMER'])
                        ->setTitle('Wochenangebote')
                        ->setUrl(preg_replace('#index\.html#', 'blaetterkatalog/pdf/complete.pdf', $singleRow['URL_HZ']))
                        ->setStoreNumber($singleRow['MARKT_ID'])
                        ->setStart(date('d.m.Y', strtotime('monday next week')))
                        ->setEnd(date('d.m.Y', strtotime('saturday next week')))
                        ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')))
                        ->setZipCode($aPostalCodes[$singleRow['FILIALNUMMER']]);

                    $cBrochures->addElement($eBrochure);
                    continue;
                } else {
                    $eBrochure->setZipCode($aPostalCodes[$singleRow['FILIALNUMMER']]);
                }
            }
            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}