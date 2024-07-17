<?php

/*
 * Brochure Crawler for Bauhaus AT (ID: 73020)
 */

class Crawler_Company_BauhausAT_Brochure extends Crawler_Generic_Company
{
    private const PUBLITAS_ACCOUNT_ID = 62220;
    private const PUBLICATION_DATE_FORMAT = 'Y-m-d';

    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleSpreadsheet;
    private string $ICID;

    public function __construct()
    {
        parent::__construct();

        $this->googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $customerData = $this->googleSpreadsheet->getCustomerData('bauhausAT');
        $this->ICID = $customerData['ICID'];
    }

    public function crawl($companyId)
    {
        $publitasService = new Marktjagd_Service_Publitas_Publications();

        $publications = $publitasService->getPublicationsFromAPI($companyId, self::PUBLITAS_ACCOUNT_ID);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($publications as $publication) {
            if (preg_match('#Flugblatt#', $publication->publicationTitle)) {
                $brochureData = $publitasService->getPublicationDataByID($companyId, $publication->id);
                $finalBrochureData = $this->overrideBrochureData($publication, $brochureData);
                $brochure = $this->createBrochure($finalBrochureData);
                $brochures->addElement($brochure);
            }
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function overrideBrochureData(object $publication, array $brochureData): array
    {
        $dateNormalization = new Marktjagd_Service_DateNormalization_Date();

        $overrides = [
            'title' => $publication->publicationTitle,
            'start' => $dateNormalization->normalize($publication->scheduleOnlineAt, self::PUBLICATION_DATE_FORMAT),
            'end' => $dateNormalization->normalize($publication->scheduleOfflineAt, self::PUBLICATION_DATE_FORMAT),
            'url' => $this->replaceICID($brochureData['url']),
        ];

        return array_merge($brochureData, $overrides);
    }

    private function replaceICID(string $brochurePath): string
    {
        if (empty($this->ICID)) {
            return $brochurePath;
        }

        $pdfService = new Marktjagd_Service_Output_Pdf();

        $pdfInfos = $pdfService->getAnnotationInfos($brochurePath);

        $clickouts = [];
        foreach ($pdfInfos as $annotation) {
            if (empty($annotation->url)) {
                continue;
            }

            // Sometimes the parameters are encoded, so we need to decode them before replacing the ICID
            $annotation->url = str_replace('%3D', '=', $annotation->url);
            $url = preg_replace('#icid=(.*)$#', 'icid=' . $this->ICID, $annotation->url);

            $clickouts[] = [
                'width' => $annotation->width,
                'height' => $annotation->height,
                'page' => $annotation->page,
                'startX' => $annotation->rectangle->startX,
                'startY' => $annotation->rectangle->startY,
                'endX' => $annotation->rectangle->endX,
                'endY' => $annotation->rectangle->endY,
                'maxX' => $annotation->maxX,
                'maxY' => $annotation->maxY,
                "link" => $url
            ];
        }

        $jsonFile = dirname($brochurePath) . 'clickoutOverrides.json';
        file_put_contents($jsonFile, json_encode($clickouts));

        $pdfService->cleanAnnotations($brochurePath);

        return $pdfService->setAnnotations($brochurePath, $jsonFile);
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($brochureData['title'])
            ->setUrl($brochureData['url'])
            ->setBrochureNumber($brochureData['number'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['start'])
            ->setVariety('leaflet');
    }
}
