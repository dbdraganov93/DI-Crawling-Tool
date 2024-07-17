<?php
/**
 * Store Crawler für La Redoute FR (ID: 72387)
 */

class Crawler_Company_LaRedouteFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.laredoute.fr/';
        $searchUrl = $baseUrl . 'espace-magasins.aspx';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<img[^>]*src="[^"]*magasin\/laredoute[^>]*>(.+?)<div[^>]*class="finfloat"#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<p[^>]*class="titre"[^>]*>\s*<strong[^>]*>(.+?\d{5}.+?)<\/div#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#horaires(.+?)<img#is';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#<\/p>#', ',', $storeHoursMatch[1]), 'text', TRUE, 'fr');
            }

            $pattern = '#<a[^>]*href="[^"]*maps[^"]*\@([^,]+?),([^,]+?),#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3], 'fr');

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}