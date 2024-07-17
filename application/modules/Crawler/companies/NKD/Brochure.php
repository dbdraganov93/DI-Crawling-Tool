<?php

/* 
 * Prospekt Crawler für NKD (ID: 342)
 */

class Crawler_Company_NKD_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.nkd.com/';
        $searchUrl = $baseUrl . 'de_de/blaetterkatalog';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?(\d{4})kw(' . date('W') . '|' . date('W', strtotime('last week')) . ')[^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure info url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Modeangebote')
            ->setUrl($brochureInfoUrlMatch[1] . 'blaetterkatalog/pdf/complete.pdf')
            ->setStart($sTimes->findDateForWeekday($brochureInfoUrlMatch[2], $brochureInfoUrlMatch[3], 'Do'))
            ->setEnd($sTimes->findDateForWeekday($brochureInfoUrlMatch[2], (int)$brochureInfoUrlMatch[3] + 2, 'Mi'))
            ->setVariety('leaflet')
            ->setBrochureNumber('kw' . $brochureInfoUrlMatch[3] . '_' . $brochureInfoUrlMatch[2]);

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
