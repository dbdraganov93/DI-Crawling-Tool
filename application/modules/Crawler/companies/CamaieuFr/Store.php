<?php
/**
 * Store Crawler für Camaieu FR (ID: 72382)
 */

class Crawler_Company_CamaieuFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.camaieu.fr/';
        $searchUrl = $baseUrl . 'store-finder?q=06000&pays=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<h3[^>]*class="title-store"[^>]*>(.+?)<\/li#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores: ' . $searchUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>(\s*<[^>]*>\s*)?\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->info($companyId . ': unable to get any store infos: ' . $singleStore);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[3]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<table[^>]*class="horaires"[^>]*>\s*(.+?)\s*</table#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fr');
            }

            $pattern = '#"latitude"\s*:\s*"([^"]+?)"\s*,\s*"longitude"\s*:\s*"([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $eStore->setStreetAndStreetNumber($aInfos['address'], 'fr')
                ->setZipcodeAndCity($aInfos['locality'])
                ->setPhoneNormalized($aInfos['telephone']);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }
}