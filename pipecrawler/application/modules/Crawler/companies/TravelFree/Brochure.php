<?php

require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/*
 * Brochure Crawler für Travel Free (ID: 70960)
 */

class Crawler_Company_TravelFree_Brochure extends Crawler_Generic_Company
{
    private const BASE_URL = 'https://www.travel-free.cz/';
    private const WEBSITE_URL = self::BASE_URL . 'angebote';
    private const BROCHURE_NUMBER_PATTERN = 'Travel_Free_%s';

    private int $companyId;

    public function crawl($companyId)
    {
        $this->companyId = (int) $companyId;

        $pageService = new Marktjagd_Service_Input_Page();
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $pageService->open(self::WEBSITE_URL);
        $page = $pageService->getPage()->getResponseBody();

        $brochureData = $this->getBrochureData($page);
        $brochure = $this->createBrochure($brochureData);
        $brochures->addElement($brochure);

        return $this->getResponse($brochures, $companyId);
    }

    private function getBrochureData(string $page): array
    {
        $pattern = '#Unsere\s*Aktionsangebote\s*vom\s*(\S+?)\s*bis\s*([^<]+?)\s*<#i';
        if (!preg_match($pattern, $page, $validityMatch)) {
            throw new Exception('Company ID: ' . $this->companyId . ': unable to get brochure validity.');
        }

        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*>\s*Angebote\s*als\s*PDF\s*<\/a>#i';
        if (!preg_match($pattern, $page, $pdfMatch)) {
            throw new Exception('Company ID: ' . $this->companyId . ': unable to get brochure pdf.');
        }

        $dateNormalization = new Marktjagd_Service_DateNormalization_Date();
        $start = $dateNormalization->normalize($validityMatch[1]);
        $end = $dateNormalization->normalize($validityMatch[2]);

        $brochurePath = $this->getBrochurePath(self::BASE_URL . $pdfMatch[1]);

        return [
            'number' => sprintf(self::BROCHURE_NUMBER_PATTERN, $start),
            'title' => 'Travel Free: Aktionsangebote',
            'url' => $brochurePath,
            'start' => $start,
            'end' => $end,
            'visibleStart' => $start,
        ];
    }

    private function getBrochurePath(string $pdfUrl): string
    {
        $http = new Marktjagd_Service_Transfer_Http();
        $localPath = $http->generateLocalDownloadFolder($this->companyId);
        $brochurePath = $http->getRemoteFile($pdfUrl, $localPath);

        return $this->addClickouts($brochurePath);
    }

    private function addClickouts(string $pdfFile): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $pageInfos = $pdfService->getAnnotationInfos($pdfFile);
        $coordinates = [];
        foreach ($pageInfos as $annotation) {
            $coordinates[] = [
                'page' => $annotation->page,
                'height' => $annotation->height,
                'width' => $annotation->width,
                'startX' => $annotation->width / 2 - 5,
                'endX' => $annotation->width / 2 + 5,
                'startY' => $annotation->height / 2 - 5,
                'endY' => $annotation->height / 2 + 5,
                'link' => self::WEBSITE_URL
            ];
        }

        $localPath = dirname($pdfFile);
        $coordFileName = $localPath . '/coordinates_' . $this->companyId . '.json';
        file_put_contents($coordFileName, json_encode($coordinates));;

        return $pdfService->setAnnotations($pdfFile, $coordFileName);
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setBrochureNumber($brochureData['number'])
            ->setTitle($brochureData['title'])
            ->setUrl($brochureData['url'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visibleStart'])
            ->setVariety('leaflet');
    }
}
