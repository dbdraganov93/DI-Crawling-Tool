<?php

/*
 * Prospekt Crawler für Intersport AT (ID: 72293)
 */

class Crawler_Company_IntersportAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.intersport.at/';
        $searchUrl = $baseUrl . 'flugblaetter-aktuelles/kataloge';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="catalogue__info"[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>\s*</div>#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureMatches[1] as $singleBrochure) {
            $pattern = '#<h3[^>]*>\s*([A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleBrochure, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure title: ' . $singleBrochure);
                continue;
            }

            $pattern = '#<a[^>]*href="\/([^"]+?\.pdf)"#';
            if (!preg_match($pattern, $singleBrochure, $urlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure title: ' . $singleBrochure);
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($titleMatch[1])
                ->setUrl($baseUrl . $urlMatch[1])
                ->setVariety('leaflet');

            if (preg_match('#' . date(Y) . '#', $eBrochure->getTitle())) {
                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
