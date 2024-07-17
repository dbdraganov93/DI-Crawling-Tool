<?php
/**
 * Store Crawler für Picard FR (ID: 72378)
 */

class Crawler_Company_PicardFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://magasins.picard.fr/';
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

            $pattern = '#<address[^>]*>\s*<div[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $streetMatch)) {
                $this->_logger->err($companyId . ': unable to get store street:' . $storeDetailUrl);
                continue;
            }

            $pattern = '#itemprop="([^"]+?)"[^>]*content="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $additionalInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get additional store infos:' . $storeDetailUrl);
                continue;
            }

            $aAdditionalInfos = array_combine($additionalInfoMatches[1], $additionalInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($streetMatch[1], 'fr')
                ->setZipcode($aInfos['postalCode'])
                ->setCity(ucwords(strtolower($aInfos['addressLocality'])))
                ->setPhoneNormalized($aInfos['telephone'])
                ->setStoreHoursNormalized($aAdditionalInfos['openingHours'])
                ->setLatitude($aAdditionalInfos['latitude'])
                ->setLongitude($aAdditionalInfos['longitude'])
                ->setStoreNumber($singleJStoreEntry->id)
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}