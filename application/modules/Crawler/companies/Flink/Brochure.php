<?php

/**
 * Brochure crawler for Flink (ID: 89745)
 */
class Crawler_Company_Flink_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $localPath = $sFtp->connect($companyId, TRUE);

        $aBrochures = [];
        $sAssignment = '';
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\s+([^\s^\.]+)\.pdf#', $singleRemoteFile, $cityMatch)) {
                $aBrochures[$cityMatch[1]] = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            } elseif (preg_match('#\.xlsx?$#', $singleRemoteFile)) {
                $sAssignment = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($sAssignment)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochures as $city => $brochurePath) {
            foreach ($aData as $singleSpreadsheet) {
                if (preg_match('#' . $city . '#', $singleSpreadsheet->getTitle())) {
                    foreach ($singleSpreadsheet->getData() as $singleRow) {
                        $aPostalCodes[] = $singleRow[1];
                    }
                    if (!count($aPostalCodes)) {
                        throw new Exception('No postal codes found for city ' . $city);
                    }
                    $postalCodes = implode(',', $aPostalCodes);
                    break;
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Flink: Flink rundet ab!')
                ->setUrl($brochurePath)
                ->setZipCode($postalCodes)
                ->setStart('11.09.2023')
                ->setEnd('23.09.2023')
                ->setVisibleStart($eBrochure->getStart());

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}
