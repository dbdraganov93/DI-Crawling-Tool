<?php

/**
 * Prospekt Crawler für Karstadt (ID: 98)
 */
class Crawler_Company_Karstadt_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $week = 'this';
        $baseUrl = 'https://www.galeria.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern= '#<a[^>]+href=\"([^\"]+)\">Aktuelle Werbung#';
        if (!preg_match($pattern, $page, $brochureUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure url.');
        }

        $sPage->open($baseUrl . $brochureUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(https:\/\/galeria\.imageserver\.laudert\.de\/galeria_webkatalog\/([^\/]+?))/index\.html#';
        if (!preg_match($pattern, $page, $brochureIdMatch)) {
            throw new Exception($companyId . ': unable to get brochure id.');
        }

        $brochureRemotePath = $brochureIdMatch[1] . '/content/pdf/' . preg_replace('#_#', '', $brochureIdMatch[2]) . '.pdf';
        if (!$sPage->checkUrlReachability($brochureRemotePath)) {
            throw new Exception($companyId . ': unable to reach brochure pdf url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Aktuelle Werbung')
            ->setUrl($brochureRemotePath)
            ->setBrochureNumber('KW' . date('W', strtotime($week . ' wednesday')) . '_' . date('Y', strtotime($week . ' wednesday')))
            ->setStart(date('d.m.Y', strtotime($week . ' wednesday')))
            ->setEnd(date('d.m.Y', strtotime($eBrochure->getStart() . '+6 days')))
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

}
