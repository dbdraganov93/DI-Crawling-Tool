<?php
/**
 * Brochure Crawler für Action (ID: 73604)
 */

class Crawler_Company_ActionAt_Brochure extends Crawler_Generic_Company
{

    protected object $sTimes;
    protected string $tmpFolder;
    protected string $weekStart;
    protected string $weekEnd;
    protected const API_URL = 'https://api.folders.nl/api/ext/action-pdf/pdfs?opdontcache=1';

    public function crawl($companyId)
    {
        $this->sTimes = new Marktjagd_Service_Text_Times();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $this->tmpFolder = $sHttp->generateLocalDownloadFolder($companyId);

        if ($this->sTimes->isDateAhead(date('d.m.Y', strtotime("this week tuesday"))))
        {
            $this->weekStart = 'last';
            $this->weekEnd = 'this';
        }
        else {
            $this->weekStart = 'this';
            $this->weekEnd = 'next';
        }

        $localBrochure = $sHttp->getRemoteFile($this->getPdfPath(), $this->tmpFolder);
        $fileNameInserted = $this->outputPdf($localBrochure);

        # add the JSON elements to the pdf template
        $eBrochure->setTitle('Action: Kleine Preise, große Freude')
            ->setUrl($fileNameInserted)
            ->setBrochureNumber("ACTION_{$this->sTimes->getWeeksYear($this->weekStart)}_KW{$this->sTimes->getWeekNr($this->weekStart)}")
            ->setStart(date('d.m.Y', strtotime("{$this->weekStart} week wednesday")))
            ->setEnd(date('d.m.Y', strtotime("{$this->weekEnd} week tuesday")) . " 23:59:59")
            ->setVisibleStart(date('d.m.Y', strtotime("{$this->weekStart} week wednesday")))
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

    private function outputPdf($localBrochure): string
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();

        # add our tracking to the existing clickout links
        $utm = '?utm_source=wogibtwas&utm_medium=folder&utm_campaign=Promo-dynamic-folder_awareness_display_wogibtwas_at&utm_content=dynamic_folder';
        $annotationInfos = $sPdf->getAnnotationInfos($localBrochure);
        $annotations = [];

        foreach ($annotationInfos as $annotation) {
            if ('Link' == !$annotation->subtype) {
                continue;
            }
            $annotation->url .= strpos($annotation->url, '?') === FALSE ? $utm : str_replace('?', '&', $utm);
            $annotations[] = [
                'width' => $annotation->width,
                'height' => $annotation->height,
                'page' => $annotation->page,
                'startX' => $annotation->rectangle->startX,
                'startY' => $annotation->rectangle->startY,
                'endX' => $annotation->rectangle->endX,
                'endY' => $annotation->rectangle->endY,
                'maxX' => $annotation->maxX,
                'maxY' => $annotation->maxY,
                "link" => $annotation->url
            ];
        }

        $sPdf->cleanAnnotations($localBrochure);
        $jsonFile = $this->tmpFolder .'clickouts.json';
        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($annotations));
        fclose($fh);

        return $sPdf->setAnnotations($localBrochure, $jsonFile);
    }

    /**
     * @throws Exception
     */
    private function getPdfPath(): string
    {
        $date = new DateTime();
        $weekNumber = (int)$date->format("W");

        $contents = file_get_contents(self::API_URL);
        $jsonData = json_decode($contents);
        $pdfUrl = '';

        foreach ($jsonData as $data) {
           foreach ($data as $key) {
               if ($key->retailer_slug == 'action-at' && strpos($key->slug, '-' . $weekNumber . '-')) {
                   if (strpos($key->url, '.pdf')) {
                       $pdfUrl = $key->url;
                       break 2;
                   }
               }
           }
        }
        if (!$pdfUrl) {
            throw new Exception('PDF NAME NOT FOUND');
        }
        return $pdfUrl;
    }
}