<?php

/*
 * Brochure Crawler für Aldi Nord (ID: 30)
 */

class Crawler_Company_Aldi_BrochureNord extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.aldi-nord.de';
        $searchUrl = $baseUrl . '/prospekte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTime = new Marktjagd_Service_Text_Times();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfos = $sGSRead->getCustomerData('aldiNordGer');

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h[^>]*>\s*ALDI(\s+Vorschau|\s+Aktuell)?\s*<\/h[^>]*>(.+?)<\/a>#is';
        if (!preg_match_all($pattern, $page, $brochureListMatches)) {
            throw new Exception($companyId . ': unable to get brochure list.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureListMatches[2] as $singleBrochure) {
            $pattern = '#\s*(\d{2}\.\d{2}\.)\s*(\d{4})?\s*#';
            if (!preg_match($pattern, $singleBrochure, $validStartMatch)) {
                throw new Exception($companyId . ': unable to get brochure validity start.');
            }

            $pattern = '#<p[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleBrochure, $titleHashMatch)) {
                throw new Exception($companyId . ': unable to get brochure title hash.');
            }

            $pattern = '#<a[^>]*href="([^"]+?)"#';
            if (!preg_match($pattern, $singleBrochure, $brochureUrlMatch)) {
                throw new Exception($companyId . ': unable to get brochure url.');
            }

            $sPage->open($baseUrl . $brochureUrlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#"paperUrl":"([^"]+?)"#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure infos.');
                continue;
            }
            $brochureBaseUrl = $infoMatch[1] . 'GetPDF.ashx';

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $localFilePath = $localPath . md5($titleHashMatch[1]) . '.pdf';

            $fh = fopen($localFilePath, 'w+');
            $ch = curl_init($brochureBaseUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            curl_setopt($ch, CURLOPT_FILE, $fh);
            $brochureFile = curl_exec($ch);
            curl_close($ch);

            if (!fwrite($fh, $brochureFile)) {
                throw new Exception($companyId . ': unable to write brochure file');
            }
            fclose($fh);
            $aPdfInfos = $sPdf->getAnnotationInfos($localFilePath);
            $aClickouts = [];
            foreach ($aPdfInfos as $singlePage) {
                $aClickouts[] = [
                    'page' => $singlePage->page,
                    'height' => $aPdfInfos[0]->height,
                    'width' => $aPdfInfos[0]->width,
                    'startX' => $aPdfInfos[0]->width / 2 - 10,
                    'endX' => $aPdfInfos[0]->width / 2 + 10,
                    'startY' => $aPdfInfos[0]->height - 10,
                    'endY' => $aPdfInfos[0]->height - 20,
                    'link' => $aInfos['clickout_' . date('n')]
                ];
            }

            $coordFileName = $localPath . 'coordinates_' . $companyId . '.json';
            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($aClickouts));
            fclose($fh);

            $localFilePath = $sPdf->setAnnotations($localFilePath, $coordFileName);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $strStart = $validStartMatch[1] . $sTime->getWeeksYear();
            if (count($validStartMatch) == 3) {
                $strStart = $validStartMatch[1] . $validStartMatch[2];
            }

            $eBrochure->setTitle('ALDI Nord: Wochenangebote')
                ->setUrl($localFilePath)
                ->setStart($strStart)
                ->setEnd(date('d.m.Y', strtotime($strStart . ' +5 days')))
                ->setVisibleStart(date('d.m.Y', strtotime($strStart . ' -1 day')))
                ->setVariety('leaflet')
                ->setBrochureNumber('KW' . date('W', strtotime($eBrochure->getStart())) . '_' . date('Y', strtotime($eBrochure->getStart())))
                ->setTrackingBug($aInfos['trackingBug_' . date('n')]);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
