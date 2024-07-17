<?php

/*
 * Store Crawler für Telekom (ID: 28829)
 */

class Crawler_Company_Telekom_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $sFTP = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        // -- Change here! -- For now this is manual
        $brochureName = 'Glasfaser VR.pdf';
        $excelZipsFile = 'PLZ_Targeting_April_Glasfaser.xlsx';
        $brochureNumber = 'Glasfaser VR_Q2';
        $brochureTitle = 'Telekom: Glasfaser';
        $trackingBug = 'https://a1.adform.net/adfserve/?bn=54113483;1x1inv=1;srctype=3;gdpr=${gdpr};gdpr_consent=${gdpr_consent_50};ord=%%CACHEBUSTER%%';
        $startDate = '01.04.2022';
        $endDate = '30.06.2022';
        $salesRegion = 'Glasfaser';
        // -- Change here! --

        $localFolder = $sFTP->connect($companyId, true);

        $localPdf = null;
        $localExcel = null;
        foreach($sFTP->listFiles() as $singleFile) {
            if(preg_match('#' . $brochureName . '#', $singleFile)) {
                $this->_logger->info('found brochure to import - ' . $singleFile);
                $localPdf = $sFTP->downloadFtpToDir($singleFile, $localFolder);
            }
            if(preg_match('#' . $excelZipsFile . '#', $singleFile)) {
                $this->_logger->info('found Excel to import - ' . $singleFile);
                $localExcel = $sFTP->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $aData = $sExcel->readFile($localExcel, true)->getElement(0)->getData();

        $zipList = [];
        foreach ($aData as $data) {
            if (empty($data['PLZ'])) {
                continue;
            }

            $zipList[] = $data['PLZ'];
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle($brochureTitle)
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setBrochureNumber($brochureNumber)
            ->setVariety('leaflet')
            ->setUrl($localPdf)
            ->setZipCode(implode(',', $zipList))
            ->setTrackingBug($trackingBug)
            ->setDistribution($salesRegion)
        ;

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}