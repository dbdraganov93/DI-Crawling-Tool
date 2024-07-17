<?php

class Crawler_Company_Shopfully_Brochure extends Crawler_Generic_Company
{
    private $localPath;
    private const DEFAULT_LANG = 'it_it';
    private const DEFAULT_DATE_FORMAT = 'd-m-Y';
    private const DEFAULT_DATETIME_FORMAT = 'd-m-Y H:i:s';

    public function crawl($companyId)
    {
        $shopfullyDatas = (new Crawler_Company_Shopfully_Store())->getBrochuresData();

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($shopfullyDatas as $shopfullyData) {
            $language = $shopfullyData['country'] ?: self::DEFAULT_LANG;
            // Is mendatory to pass the language to the API
            $api = new Shopfully_Service_BrochureApi($language);
            // We need to pass the shopfully flyer to the API to get all flyer data
            $brochureData = $api->getBrochure($shopfullyData['brochureId']);

            $sHttp = new Marktjagd_Service_Transfer_Http();
            $this->localPath = $sHttp->generateLocalDownloadFolder($companyId);

            $pdfPatch = $sHttp->getRemoteFile($brochureData->getPdfUrl(), $this->localPath);
            $pdfWithClickoutsPatch = $this->addClickouts($brochureData->getClickouts(), $pdfPatch);

            $brochure = $this->createBrochure($brochureData, $pdfWithClickoutsPatch);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function addClickouts(array $clickouts, string $pdf): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $annotationInfos = $pdfService->getAnnotationInfos($pdf);
        $annotationInfo = reset($annotationInfos);
        $annotations = [];

        foreach ($clickouts as $clickout) {
            $startX = $clickout->getX() * $annotationInfo->width;
            $startY = $annotationInfo->height - $clickout->getY() * $annotationInfo->height;

            if (0 == $clickout->getY() || ($startY + 5 > $annotationInfo->height)) {
                $startX = ($annotationInfo->width - 5) / 2;
                $startY = ($annotationInfo->height - 5) / 2;
            }

            $annotations[] = [
                'width' => $annotationInfo->width,
                'height' => $annotationInfo->height,
                'page' => $clickout->getPageNumber() - 1,
                'startX' => $startX,
                'startY' => $startY,
                'endX' => $startX + 5,
                'endY' => $startY + 5,
                'maxX' => $annotationInfo->maxX,
                'maxY' => $annotationInfo->maxY,
                "link" => $clickout->getClickout()
            ];
        }

        $pdfService->cleanAnnotations($pdf);
        $jsonFile = $this->localPath . 'clickouts.json';
        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($annotations));
        fclose($fh);

        # add the JSON elements to the pdf template and return the file path
        return $pdfService->setAnnotations($pdf, $jsonFile);
    }

    private function createBrochure(Shopfully_Entity_Brochure $brochureData, string $pdfPath): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setUrl($pdfPath)
            ->setBrochureNumber($brochureData->getId())
            ->setTitle($brochureData->getTitle())
            ->setStart($brochureData->getStartDate()->format(self::DEFAULT_DATE_FORMAT))
            ->setEnd($brochureData->getEndDate()->format(self::DEFAULT_DATE_FORMAT))
            ->setVisibleStart($brochureData->getPublishAt()->format(self::DEFAULT_DATETIME_FORMAT))
            ->setVariety('leaflet');

        $stores = $brochureData->getStores();
        if (!empty($stores)) {
            $brochure->setStoreNumber(implode(',', $stores));
        }

        return $brochure;
    }
}
