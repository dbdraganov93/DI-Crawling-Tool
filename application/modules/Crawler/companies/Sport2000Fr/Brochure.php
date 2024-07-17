<?php
/**
 * Brochure Crawler für Sport 2000 FR (ID: 72383)
 */

class Crawler_Company_Sport2000Fr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sport2000.fr/';
        $searchUrl = $baseUrl . 'catalogues.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#class="catItemTitle">\s*(.+?)<\/div>\s*<\/div#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureMatches[1] as $singleBrochure) {
            $pattern = '#^<a[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleBrochure, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure title: ' . $singleBrochure);
                continue;
            }

            $pattern = '#<a[^>]*href="\/([^"]+?\.pdf)"#';
            if (!preg_match($pattern, $singleBrochure, $pdfUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure pdf url: ' . $singleBrochure);
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($titleMatch[1])
                ->setUrl($baseUrl . $pdfUrlMatch[1])
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}