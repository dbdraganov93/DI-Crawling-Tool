<?php

/*
 * Prospekt Crawler für Möbel Boss (ID: 66)
 */

class Crawler_Company_MoebelBoss_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect('108');
        $sFtp->changedir('./SB_Moebel_Boss');

        $week = 'next';

        $weekToCheck = $sTimes->getWeekNr($week);
        $yearToCheck = $sTimes->getWeeksYear($week);


        $pattern = '#SB' . $weekToCheck . '[^-]*-' . $yearToCheck . '#';
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (preg_match($pattern, $singleFolder)) {
                $sFtp->changedir($singleFolder);
                $pattern = '#sb' . $weekToCheck . '-' . $yearToCheck . '[^\.]*\.pdf$#';
                foreach ($sFtp->listFiles() as $singleFile) {
                    if (preg_match($pattern, $singleFile)) {
                        $localFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    }
                }
            }
        }
        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Wochen Angebote')
            ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
            ->setEnd(date('d.m.Y', strtotime('sunday ' . $week . ' week')))
            ->setVisibleStart($eBrochure->getStart())
            ->setVariety('leaflet')
            ->setUrl($localFile)
            ->setBrochureNumber('kw_' . $weekToCheck . '_' . $yearToCheck);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

}
