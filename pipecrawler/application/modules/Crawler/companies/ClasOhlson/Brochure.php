<?php

/**
 * Prospekt Crawler für ClasOhlson (ID: 71941)
 */
class Crawler_Company_ClasOhlson_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $trackingParameter = '?utm_source=marktjagd&utm_medium=digital-prospekt'
            . '&utm_content=prospekt-juli&utm_campaign=juli_prospekte';

        /**
         * Campaign Parameter
         */
        $publicationId = '204a62a7';
        $version = '24';
        $startDate = '08.07.2017';
        $endDate = '05.08.2017';
        $brochureTitle = 'Juli Angebote';

        $urlPdf = 'http://viewer.zmags.com/services/DownloadPDF?publicationID=' . $publicationId
            . '&selectedPages=all&pubVersion=' . $version;
        $clickoutUrl = 'http://viewer.zmags.com/services/resource/pub/'
            . $publicationId . '/enr/'
            . $version . '/1-16?schemaVersion=2';

        $sDownload = new Marktjagd_Service_Transfer_Download();
        $localFolder = $sDownload->generateLocalDownloadFolder($companyId);
        $coordFileName = $localFolder . 'coordinates_' . $companyId . '_' . $publicationId . '_' . $version . '.json';

        $localFilePathTemp = $sDownload->downloadByUrl($urlPdf, $localFolder);
        $localFilePath = $localFilePathTemp . '.pdf';
        exec('mv ' . $localFilePathTemp . ' ' . $localFilePath);

        $aPages = $sPdf->getAnnotationInfos($localFilePath);

        $width = (float) $aPages[0]->width;
        $height = (float) $aPages[0]->height;

        $sPage->open($clickoutUrl);
        $jsonClickout = $sPage->getPage()->getResponseAsJson();

        $aCoordsToLink = array();

        foreach ($jsonClickout as $clickout) {
            $aCoordsToLink[] = array(
                'page' => (int) $clickout->toPageNumber - 1,
                'height' => $height,
                'width' => $width,
                'startX' => (float) $clickout->x * $width,
                'endX' => ((float) $clickout->x * $width) + ((float) $clickout->width * $width),
                'startY' => $height - (float) $clickout->y * $height,
                'endY' => $height - ((float) $clickout->y * $height) - ((float) $clickout->height * $height),
                'link' => trim($clickout->url) . $trackingParameter);
        }

        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);


        $localFilePath = $sPdf->setAnnotations($localFilePath, $coordFileName);

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setUrl($sCsv->generatePublicBrochurePath($localFilePath))
                ->setVariety('leaflet')
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($startDate)
                ->setVisibleEnd($endDate)
                ->setTitle($brochureTitle)
                ->setBrochureNumber($publicationId . '_' . $version);

        $cBrochures->addElement($eBrochure, TRUE);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
