<?php
/**
 * Store Crawler für BUT FR (ID: 72329)
 */

class Crawler_Company_ButFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.but.fr/';
        $searchUrl = $baseUrl . 'magasins/recherche-magasins.php';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select[^>]*id="liste_mag"[^>]*>(.+?)<\/select>#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<option[^>]*value="(\d+?)"[^>]*id="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeMatches[0]); $i++) {
            $storeDetailUrl = $baseUrl . 'magasins/' . $storeMatches[1][$i] . '/' . preg_replace('#_#', '-', $storeMatches[2][$i]) . '.html';

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="encart-adresse-mag"[^>]*>(.+?)<\/section#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $infoListMatch[1], $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos from list: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*class="jour[^>]*>(.+?)<\/div#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(preg_replace('#\|#', ',', implode(',', $storeHoursMatches[1])), 'text', TRUE, 'fra');
            }

            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                ->setZipcode($aInfos['postalCode'])
                ->setCity(ucwords(strtolower($aInfos['addressLocality'])))
                ->setStoreNumber($storeMatches[1][$i])
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }
}
