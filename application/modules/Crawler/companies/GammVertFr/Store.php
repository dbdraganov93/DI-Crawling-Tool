<?php
/**
 * Store Crawler für Gamm vert FR (ID: 72376)
 */

class Crawler_Company_GammVertFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://magasin.gammvert.fr/';
        $searchUrl = $baseUrl . 'fr';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*allPosMarker\s*=\s*\'([^\']+?)\'\s*;#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1]) as $singleJStoreEntry) {
            $storeDetailUrl = $baseUrl . $singleJStoreEntry->id;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]{3,}?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos:' . $storeDetailUrl);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $pattern = '#itemprop="([^"]+?)"[^>]*content="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $additionalInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get additional store infos:' . $storeDetailUrl);
                continue;
            }

            $aAdditionalInfos = array_combine($additionalInfoMatches[1], $additionalInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<h3[^>]*store__services[^>]*>(\s*<a[^>]*>\s*)?([^<]+?)\s*<#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[2]));
            }

            $pattern = '#<h3[^>]*store__products[^>]*>(\s*<a[^>]*>\s*)?([^<]+?)\s*<#';
            if (preg_match_all($pattern, $page, $sectionMatches)) {
                $eStore->setSection(implode(', ', $sectionMatches[2]));
            }

            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'], 'fr')
                ->setZipcode($aInfos['postalCode'])
                ->setCity(ucwords(strtolower($aInfos['addressLocality'])))
                ->setPhoneNormalized($aInfos['telephone'])
                ->setStoreHoursNormalized($aAdditionalInfos['openingHours'])
                ->setLatitude($aAdditionalInfos['latitude'])
                ->setLongitude($aAdditionalInfos['longitude'])
                ->setStoreNumber($singleJStoreEntry->id)
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}