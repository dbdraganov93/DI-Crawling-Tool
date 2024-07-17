<?php

/**
 * Brochure-Crawler für Medimax (ID: 101)
 */
class Crawler_Company_Medimax_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $url = 'https://www.medimax.de/angebote/';
        $sPage = new Marktjagd_Service_Input_Page();
        foreach ($sPage->getDomElsFromUrlByClass($url, 'banner-link', 'a') as $item) {
            $brochureUrl = $item->getAttribute('href');
            if (preg_match('#\.pdf#', $brochureUrl)) {
                break;
            }
        };
        if (!isset($brochureUrl)) {
            throw new Exception('No link available with a PDF file as target');
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Medimax: Wochenangebote')
            ->setUrl($brochureUrl)
            ->setStart(date('d.m.Y', strtotime('monday this week')))
            ->setEnd(date('d.m.Y', strtotime('saturday this week')))
            ->setVisibleStart($eBrochure->getStart())
            ->setVariety('leaflet');

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}