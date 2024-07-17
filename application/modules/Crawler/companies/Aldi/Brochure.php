<?php

/**
 * Brochure Crawler für Aldi Süd (ID: 29)
 */
class Crawler_Company_Aldi_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '2G');
        $baseUrl = 'https://www.aldi-sued.de/';
        $searchUrl = $baseUrl . 'de/angebote/prospekte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfos = $sGSRead->getCustomerData('aldiSuedGer');

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?kw(' . date('W') . '|' . date('W', strtotime('next week')) . ')[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $brochureUrlMatches)) {
            throw new Exception($companyId . ': unable to get any brochure urls.');
        }
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureUrlMatches[1] as $singleBrochureUrl) {
            $sPage->open($singleBrochureUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#downloadPdfUrl"\s*:\s*"([^"]+?)"#';
            if (!preg_match($pattern, $page, $downloadUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get download url for ' . $singleBrochureUrl);
                continue;
            }

            $localFilePath = $sHttp->getRemoteFile($downloadUrlMatch[1], $localPath);

            $pattern = '#<meta[^>]*name="description"[^>]*content="[^\d]+(\d+\.\d+\.(\d{4})?)[^"]+\s+(\d+\.\d+\.(\d{4})?)"#';
            if (!preg_match($pattern, $page, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity: ' . $singleBrochureUrl);
                continue;
            }

            if (!strlen($validityMatch[2])) {
                $validityMatch[1] .= date('Y');
            }
            if (count($validityMatch) != 5) {
                $validityMatch[3] .= date('Y');
            }

            $aPdfInfos = $sPdf->getAnnotationInfos($localFilePath);

            $aClickouts[] = [
                'page' => 0,
                'height' => $aPdfInfos[0]->height,
                'width' => $aPdfInfos[0]->width,
                'startX' => $aPdfInfos[0]->width / 2 - 120,
                'endX' => $aPdfInfos[0]->width / 2 - 110,
                'startY' => $aPdfInfos[0]->height - 10,
                'endY' => $aPdfInfos[0]->height - 20,
                'link' => $aInfos['clickout_' . date('n')]
            ];

            $coordFileName = $localPath . 'coordinates_' . $companyId . '.json';
            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($aClickouts));
            fclose($fh);

            $localFilePath = $sPdf->setAnnotations($localFilePath, $coordFileName);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Aldi Süd: Wochenangebote')
                ->setUrl($localFilePath)
                ->setStart($validityMatch[1])
                ->setEnd(date('d.m.Y', strtotime($validityMatch[1] . ' +5 days')))
                ->setVisibleStart(date('d.m.Y', strtotime($validityMatch[1] . ' -1 day')))
                ->setBrochureNumber('KW' . date('W', strtotime($validityMatch[1])) . '_' . date('Y', strtotime($validityMatch[1])))
                ->setVariety('leaflet')
                ->setTrackingBug($aInfos['trackingBug_' . date('n')]);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}