<?php
/**
 * Brochure Crawler für Screwfix (ID: 72086)
 */

class Crawler_Company_Screwfix_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.screwfix.de/';
        $searchUrl = $baseUrl . 'prospekt-angebote';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*title="Prospekt[^"]+?runterladen[^>]*href="([^"]+?([^\/\.]+?)\.pdf)"#';
        if (!preg_match($pattern, $page, $pdfUrlMatch)) {
            throw new Exception($companyId . ': unable to get pdf url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Prospekt Angebote')
            ->setUrl($pdfUrlMatch[1])
            ->setBrochureNumber($pdfUrlMatch[2])
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}