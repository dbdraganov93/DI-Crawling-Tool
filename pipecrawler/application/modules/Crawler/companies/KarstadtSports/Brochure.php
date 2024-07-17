<?php
/**
 * Prospekt Crawler für Karstadt Sports (ID: 67152)
 */

class Crawler_Company_KarstadtSports_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.karstadtsports.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?KW' . date('W') . '\.pdf)"#';
        if (!preg_match($pattern, $page, $brochureUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($brochureUrlMatch[1])
            ->setTitle('Wochenangebote')
            ->setStart(date('d.m.Y', strtotime('monday this week')))
            ->setEnd(date('d.m.Y', strtotime('saturday this week')))
            ->setVariety('leaflet')
        ->setBrochureNumber('KW' . date('W') . '_' . $sTimes->getWeeksYear());

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}