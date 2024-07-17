<?php

/**
 * Brochure Crawler für DeutschlandCard (ID: 90227)
 */

class Crawler_Company_DeutschlandCard_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#(\d{2}\.\d{2}\.\d{4})\.pdf$#', $singleFile, $dateMatch)
                && strtotime($dateMatch[1]) >= strtotime('today 00:00:00')
            ) {
                $localBrochureFiles[$dateMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $inputFile = $localBrochureFiles[$dateMatch[1]];
                $outputFile = preg_replace('#\.pdf#', '_fixed.pdf', $inputFile);
                exec('gs -o ' . $outputFile . ' -sDEVICE=pdfwrite ' . $inputFile);
                $localBrochureFiles[$dateMatch[1]] = $outputFile;
            }
        }
        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($localBrochureFiles as $localBrochureFile) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('DeutschlandCard: Aktueller Spieltag')
                ->setBrochureNumber('DLC_EM_Spezial_2024')
                ->setVisibleStart('27.05.2024')
                ->setUrl($localBrochureFile)
                ->setNational(TRUE);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}