<?php
/**
 * Brochure Crawler für La Redoute FR (ID: 72387)
 */

class Crawler_Company_LaRedouteFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.laredoute.fr/';
        $searchUrl = $baseUrl . 'espace-catalogues.aspx';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*class="ima"[^>]*href="(https:\/\/cdn\.laredoute\.com\/catalogues\/[^"]+?)"[^>]*>\s*<img[^>]*>\s*<\/a>\s*<h[^>]*>\s*([^<]+?)\s*<#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        for ($i = 0; $i < count($brochureMatches[1]); $i++) {
            $brochurePdfUrl = $brochureMatches[1][$i] . '/common/data/catalogue.pdf';

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($brochurePdfUrl)
                ->setTitle($brochureMatches[2][$i])
                ->setVariety('leaflet')
                ->setTags('détergent, fromage, nettoyeur, les vêtements féminins, savon, nourriture pour chats, chaussettes, vin, saumon, pommes');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}