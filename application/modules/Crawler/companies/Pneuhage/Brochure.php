<?php

/*
 * Brochure Crawler für Pneuhage (ID: 29002), Ehrhardt Reifen (ID: 70838)
 */

class Crawler_Company_Pneuhage_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aPattern = array(
            '29002' => array(
                'pattern' => '#Pneu_(\d{8})-(\d{8})\.pdf#i'
            ),
            '70838' => array(
                'pattern' => '#ERA_(\d{8})-(\d{8})\.pdf#i',

            ),
            '29123' => array(
                'pattern' => '#FST_(\d{8})-(\d{8})\.pdf#i',

            )
        );

        $sFtp->connect('29002');
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $localBrochurePath = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($aPattern[$companyId]['pattern'], $singleFile, $validityMatch)) {
                $localBrochurePath[] = [
                    'path' => $sFtp->downloadFtpToDir($singleFile, $localPath),
                    'date1' => $validityMatch[1],
                    'date2' => $validityMatch[2],
                ];
            }
        }

        $aModification = array(
            array(
                'searchPattern' => '(.+)',
                'replacePattern' => '$1?campaign=DP/ERA/Beilage1/FS2018/Offerista'
            )
        );

        $fileName = $localPath . 'exchangeData.json';

        $fh = fopen($fileName, 'w+');
        fwrite($fh, json_encode($aModification));
        fclose($fh);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($localBrochurePath as $singleBrochurePath) {
            $localBrochurePathExchanged = $sPdf->exchange($singleBrochurePath['path']);
            if ($companyId == 70838) {
                $localBrochurePathExchanged = $sPdf->modifyLinks($localBrochurePathExchanged, $fileName);
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Reifen Angebote')
                ->setUrl($sFtp->generatePublicFtpUrl($localBrochurePathExchanged))
                ->setStart(preg_replace('#(\d{2})(\d{2})(\d{4})#', '$1.$2.$3', $singleBrochurePath['date1']))
                ->setEnd(preg_replace('#(\d{2})(\d{2})(\d{4})#', '$1.$2.$3', $singleBrochurePath['date2']))
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

}
