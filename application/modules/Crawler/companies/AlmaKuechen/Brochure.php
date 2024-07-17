<?php
/**
 * Brochure Crawler für Alma Küchen (ID: 71001)
 */

class Crawler_Company_AlmaKuechen_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $searchUrl = 'https://www.alma-kuechen.de/katalog/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTime = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*Katalog[^>]*herunterladen[^>]*>#';
        if (!preg_match($pattern, $page, $pdfUrlMatch)) {
            throw new Exception($companyId . ': unable to get pdf url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Modellübersicht ' . $sTime->getWeeksYear())
            ->setUrl($pdfUrlMatch[1])
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}