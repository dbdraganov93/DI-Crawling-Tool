<?php

/** Brochure crawler for Vedes and Vedes AT (ID: 28654, 82522) */

class Crawler_Company_Vedes_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $aCountry = [
            28654 => 'DE',
            82522 => 'AT',
        ];
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $localPath = $sFtp->connect('28654', TRUE);

        $localBrochureFiles = [];
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#([^.]+?)\.pdf$#', $singleRemoteFile, $nameMatch)) {
                $localBrochureFiles[$nameMatch[1]] = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }
        $sFtp->close();
        $aBrochureData = $sGSRead->getFormattedInfos('1-q-NhKP5T6D3ThnRwGBU-eexBO3d4tzzx3YqS3NMc7A', 'A1', 'R', 'Vedes Easter Flyer ' . $aCountry[$companyId] . ' 2024');

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochureData as $singleRow) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($singleRow['Clickouts Briefing'] == 'Bitte den direkten Produkt-Link aus der Produktliste nehmen!' ? $localBrochureFiles['mit Artikeln URLs'] : $localBrochureFiles['mit 1 Clickout per Seite'])
                ->setBrochureNumber($singleRow['brochure number'])
                ->setStoreNumber($singleRow['store_number'])
                ->setStart($singleRow['active_period_from_date'])
                ->setEnd($singleRow['active_period_to_date'])
                ->setVisibleStart($eBrochure->getStart())
                ->setTitle(trim($singleRow['Brochure Name']));

            $cBrochures->addElement($eBrochure, FALSE);

            if ($singleRow['Clickouts Briefing'] == 'Bitte den direkten Produkt-Link aus der Produktliste nehmen!') {
                $localBrochureFiles['mit Artikeln URLs'] = $eBrochure->getUrl();
            } else {
                $localBrochureFiles['mit 1 Clickout per Seite'] = $eBrochure->getUrl();
            }

        }

        return $this->getResponse($cBrochures);
    }
}