<?php

/**
 * Brochure crawler for EDEKA Hessenring (IDs: 73681, 80195-80197)
 */

class Crawler_Company_EdekaHessenring_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $week = 'next';

        $aDistributions = [
            '73681' => '#EDEKA#',
            '80195' => '#Marktkauf#',
            '80196' => '#E\s+neukauf#',
            '80197' => '#E\s+aktiv\s+markt#'
        ];

        $localPath = $sFtp->connect('2', TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.xlsx$#', $singleRemoteFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localBrochurePath, TRUE)->getElement(7)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aData as $singleRow) {
            if (!preg_match('#HR#', $singleRow['REGION_KÜRZEL'])
                || !preg_match($aDistributions[$companyId], $singleRow['VERTRIEBSSCHIENE'])
                || is_null($singleRow['URL_HZ'])) {
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochenangebote')
                ->setUrl(preg_replace('#index\.html#', 'blaetterkatalog/pdf/complete.pdf', $singleRow['URL_HZ']))
                ->setStoreNumber($singleRow['MARKT_ID'])
                ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')));

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}