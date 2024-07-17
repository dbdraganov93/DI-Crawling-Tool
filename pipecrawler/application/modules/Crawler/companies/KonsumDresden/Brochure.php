<?php

/**
 * Brochure Crawler für Konsum Dresden (ID: 264)
 */

class Crawler_Company_KonsumDresden_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.konsum.de/';
        $searchUrl = $baseUrl . 'angebote-und-service/woechentliche-angebote/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*class="real3dflipbook-downloadlink"[^>]*href="([^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureMatch)) {
            throw new Exception($companyId . ': unable to get brochure url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Wöchentliche Angebote')
            ->setUrl($brochureMatch[1])
            ->setStart(date('d.m.Y', strtotime('monday this week')))
            ->setEnd(date('d.m.Y', strtotime('saturday this week')))
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}