<?php

/**
 * Brochure crawler for Sconto (ID: 156)
 */

class Crawler_Company_Sconto_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sconto.de/';
        $searchUrl = $baseUrl . 'angebote';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#https://prospekte\.sconto\.de/prospekte/blaetterkatalog/index\.html\?item=([^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureNumberMatch)) {

            $this->_logger->info($companyId . ': no brochures found on page.');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

            return $this->_response;
        }

        $brochureUrl = 'https://prospekte.sconto.de/prospekte/blaetterkatalog/catalogs/' . $brochureNumberMatch[1] . '/pdf/complete.pdf';
        $localBrochurePath = $sHttp->getRemoteFile($brochureUrl, $localPath);

        $jText = json_decode($sPdf->extractText($localBrochurePath));

        $pattern = '#Angebote\s*gültig\s*vom\s*(\d{1,2}\.\d{1,2}\.)(\d{4})?\s*bis\s*(\d{1,2}\.\d{1,2}\.\d{4})#';
        if (!preg_match($pattern, $jText[0]->text, $validityMatch)) {
            throw new Exception($companyId . ': unable to get brochure validities from pdf.');
        }

        $validityMatch[1] .= $validityMatch[2];
        if (!$validityMatch[2]) {
            $validityMatch[1] .= date('Y');
        }
        $dimensionFilePath = 'https://prospekte.sconto.de/prospekte/blaetterkatalog/catalogs/' . $brochureNumberMatch[1] . '/xml/catalog.xml';
        $xmlString = simplexml_load_file($dimensionFilePath);

        $pageWidth = (string)$xmlString->structure->detaillevel[2]->attributes()->width;
        $pageHeight = (string)$xmlString->structure->detaillevel[2]->attributes()->height;

        for ($i = 1; $i <= count($jText); $i++) {
            $xmlSite = 'https://prospekte.sconto.de/prospekte/blaetterkatalog/catalogs/' . $brochureNumberMatch[1] . '/maps/bk_' . $i . '.xml';
            $xmlData = simplexml_load_file($xmlSite);

            foreach ($xmlData->area as $singleClickout) {
                $link = 'https://' . $singleClickout->attributes()->id;
                if (preg_match('#^(\d+)$#', $singleClickout->attributes()->id)) {
                    $link = 'https://www.sconto.de/artikel/' . $singleClickout->attributes()->id;
                }
                $aCoords = preg_split('#\s*,\s*#', (string)$singleClickout->attributes()->coords);

                $endX = min($aCoords[2], 1503);
                $endY = max($pageHeight - $aCoords[3], 0);
                $aCoordsToLink[] = [
                    # for pdfbox page nr is 0-based
                    'page' => $i - 1,
                    'height' => $pageHeight,
                    'width' => $pageWidth,
                    'startX' => $aCoords[0],
                    'endX' => $endX,
                    'startY' => $pageHeight - $aCoords[1],
                    'endY' => $endY,
                    'link' => $link
                ];
            }
        }

        $coordFileName = $localPath . 'coordinates_' . $companyId . '_' . $brochureNumberMatch[1] . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        $linkedFilePath = $sPdf->setAnnotations($localBrochurePath, $coordFileName);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($linkedFilePath)
            ->setVariety('leaflet')
            ->setStart($validityMatch[1])
            ->setEnd($validityMatch[3])
            ->setTitle('SCONTO: Prospekt')
            ->setBrochureNumber($brochureNumberMatch[1]);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }
}