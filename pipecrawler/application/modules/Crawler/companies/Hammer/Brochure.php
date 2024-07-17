<?php

/* 
 * Brochure Crawler für Hammer (ID: 67475)
 */

class Crawler_Company_Hammer_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $uri = 'https://www.hammer-heimtex.de/produktwelten';
        $endDate = date('d.m.Y', strtotime("tomorrow"));
        $variety = 'leaflet';

        $sPage = new Marktjagd_Service_Input_Page();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($sPage->getDomElsFromUrlByClass($uri, 'm-teaser ') as $brochureRaw) {
            if (!$brochureUri = $this->getUri($sPage, $brochureRaw)) {
                continue;
            }
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($brochureUri)
                ->setEnd($endDate)
                ->setTitle($brochureRaw->getElementsByTagName('h3')[0]->textContent)
                ->setBrochureNumber($this->getRandomBrochureNumber())
                ->setVariety($variety);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param $sPage
     * @param $brochureRaw
     * @return bool|string
     */
    private function getUri($sPage, $brochureRaw)
    {
        $pdfUri = $brochureRaw->getElementsByTagName('a')[0]->getAttribute('href');
        $sPage->open($pdfUri);
        $page = $sPage->getPage()->getResponseBody();
        if (!preg_match('#[^\"|\.]+\.pdf#', $page, $shortUri)) {
            return false;
        }
        return dirname($pdfUri) . "/$shortUri[0]";
    }
}