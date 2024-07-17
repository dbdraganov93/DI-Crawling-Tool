<?php

/**
 * Store crawler for Plameco (ID: 82371)
 */

class Crawler_Company_Plameco_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.plameco.de/';
        $searchUrl = $baseUrl . 'verkaufsstellen-plameco';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#window\.dealers\s*=\s*\[\s*([^;]+?)\s*,\s*\]\s*;#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#\{([^\}]+?)\}#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#\'([^\']+?)\'\s*:\s*\'([^\']+?)\'#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos from ' . $singleStore);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($aInfos['latitude'])
                ->setLongitude($aInfos['longitude'])
                ->setTitle($aInfos['name'])
                ->setStreetAndStreetNumber($aInfos['street'])
                ->setZipcode($aInfos['zipCode'])
                ->setCity($aInfos['city']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

}