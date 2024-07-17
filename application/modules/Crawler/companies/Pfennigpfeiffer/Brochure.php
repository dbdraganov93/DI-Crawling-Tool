<?php

/*
 * Brochure Crawler für Pfennigpfeiffer (ID: 41)
 */

class Crawler_Company_Pfennigpfeiffer_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pfennigpfeiffer.de/';
        $sTimes = New Marktjagd_Service_Text_Times();
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();


        # build a list with stores and remove the ones from saxony
        # ticket #75986 (until further notice)
        $activeStores = $sApi->findAllStoresForCompany($companyId);

        $storeList = [];
        foreach($activeStores as $index => $activeStore) {
            if($sGeo->findShortRegionByZipCode($activeStore['zipcode']) == 'SN') {
                $this->_logger->info('removing store' . $activeStore['number'] . ' - ' . $activeStore['city'] . ' from list');
                continue;
            }
            $storeList[] = $activeStore['number'];
        }

        $week = 'next';
        $weekNr = $sTimes->getWeekNr($week);
        $year = $sTimes->getWeeksYear($week);
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        if (date('N') == 5) {
            $aFtpBrochures = array();
            foreach ($sFtp->listFiles('./' . $year, '#KW_' . str_pad($weekNr, 2, '0', STR_PAD_LEFT) . '#') as $singleFile) {
                $this->_logger->info('downloading ' . $singleFile);
                $aFtpBrochures[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (count($aFtpBrochures)) {
                foreach ($aFtpBrochures as $singleFtpBrochure) {
                    $aData = array(
                        array(
                            'page' => 0,
                            'link' => 'https://www.pfennigpfeiffer.de/',
                            'startX' => '340',
                            'endX' => '390',
                            'startY' => '740',
                            'endY' => '790'
                        )
                    );

                    $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $companyId . '.json';

                    $fh = fopen($coordFileName, 'w+');
                    fwrite($fh, json_encode($aData));
                    fclose($fh);

                    $singleFtpBrochure = $sPdf->setAnnotations($singleFtpBrochure, $coordFileName);

                    $eBrochure->setTitle('Produkte der Woche')
                        ->setBrochureNumber('KW' . $weekNr . '-' . $year)
                        ->setUrl($singleFtpBrochure)
                        ->setStart(date('d.m.Y', strtotime("monday $week week")))
                        ->setEnd(date('d.m.Y', strtotime("sunday $week week")))
                        ->setVisibleStart($eBrochure->getStart())
                        ->setVariety('leaflet')
                        ->setStoreNumber(implode(',',$storeList));


                    $cBrochures->addElement($eBrochure);
                }

                $sFtp->close();
                return $this->getResponse($cBrochures, $companyId);
            }

            $this->_logger->info($companyId . ': no ftp brochures for week: ' . $weekNr);
        }

        $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);

        if (count($aBrochures) > 0) {
            foreach ($aBrochures as $singleBrochure) {
                if (!preg_match('#Wir\s*sind\s*Schule\!#', $singleBrochure['title']) && preg_match('#KW' . date('W') . '#', $singleBrochure['brochureNumber'])) {
                    $this->_logger->info($companyId . ': brochure already existing for week: ' . date('W'));
                    $this->_response->setIsImport(false);
                    $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

                    return $this->_response;
                }
            }
        }

        $sFtp->connect($companyId);

        $aSitesToMerge = [];

        $localTitlePath = $sFtp->downloadFtpToDir($sFtp->listFiles('./title', '#title\.pdf#')[0], $localPath);
        if ($localTitlePath) {
            $aSitesToMerge[] = $localTitlePath;
        }

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<ul[^>]*class="slides"[^>]*>(.+?)</ul#';
        if (!preg_match($pattern, $page, $slideListMatch)) {
            throw new Exception($companyId . ': unable to get slide list.');
        }

        $pattern = '#<img[^>]*src="([^"]+?)"#';
        if (!preg_match_all($pattern, $slideListMatch[1], $slideListMatches)) {
            throw new Exception($companyId . ': unable to get any slide pictures from list.');
        }

        foreach ($slideListMatches[1] as $singleSlide) {
            $sHttp->getRemoteFile($singleSlide, $localPath);
        }

        foreach (scandir($localPath) as $singlePath) {
            if (!preg_match('#^\.#', $singlePath) && !preg_match('#\.pdf#', $singlePath)) {
                $this->_logger->info($companyId . ': generating pdf: ' . $singlePath);
                $sPdf->createPdf($localPath . $singlePath);
            }
        }

        foreach (scandir($localPath) as $singlePath) {
            if (preg_match('#\.pdf#', $singlePath) && !preg_match('#title\.pdf#', $singlePath)) {
                $aSitesToMerge[] = $localPath . $singlePath;
            }
        }

        $this->_logger->info($companyId . ': merging pdf.');
        $brochurePath = $sPdf->merge($aSitesToMerge, $localPath);

        $eBrochure->setTitle('Produkte der Woche')
            ->setBrochureNumber('KW' . date('W') . '-' . $sTimes->getWeeksYear())
            ->setUrl($brochurePath)
            ->setStart(date('d.m.Y', strtotime("monday $week week - 1 week")))
            ->setEnd(date('d.m.Y', strtotime("sunday $week week - 1 week")))
            ->setVisibleStart($eBrochure->getStart())
            ->setVariety('leaflet')
            ->setOptions('no_cut')
            ->setStoreNumber(implode(',',$storeList));

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}
