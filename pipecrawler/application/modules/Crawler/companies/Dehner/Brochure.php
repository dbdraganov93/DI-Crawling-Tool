<?php

/* 
 * Brochure Crawler für Dehner (ID: 355)
 */

class Crawler_Company_Dehner_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $baseUrl = 'https://www.dehner.de';
        $searchUrl = "$baseUrl/service/prospekte-kataloge-magazine/";

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($sPage->getDomElsFromUrlByClass($searchUrl, 'cms-block-text', 'div') as $block) {
            $aUrl = $sPage->getDomElsFromDomEl($block, "link-external", 'class', 'a')[0]->getAttribute('href');
            if (!preg_match('#beilage#i', $aUrl)) {
                continue;
            }

            $head = $block->getElementsByTagName('h3')[0]->textContent ?: 'Wochenangebote';
            $pUrl = $sPage->getRedirectedUrl($aUrl, $baseUrl);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($head)
                ->setUrl(preg_replace('#.pdf\?.*$#i', ".pdf", $pUrl))
                ->setVisibleStart(date('d.m.Y'))
                ->setStart($eBrochure->getVisibleStart())
                ->setEnd(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . ' +1 day')))
                ->setVariety('leaflet')
                ->setBrochureNumber($this->getRandomBrochureNumber());

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId, -1);
    }
}