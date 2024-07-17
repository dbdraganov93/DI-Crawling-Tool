<?php

/*
 * Store Crawler für SportScheck (ID: 282)
 */

class Crawler_Company_SportScheck_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sportscheck.com/';
        $searchUrl = $baseUrl . 'filialen/';

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $page = curl_exec($ch);
        curl_close($ch);

        $pattern = '#href="[^"]+?filialen\/([^"]+?)">#s';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get store url list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;

            $ch = curl_init($storeDetailUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $page = curl_exec($ch);
            curl_close($ch);

            $pattern = '#<h2[^>]*>\s*kontakt\s*<\/h2>(.+?)<br[^>]*><br[^>]*>#is';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $aStoreInfos = preg_split('#\s*<br[^>]*>\s*#', $storeInfoMatch[1]);

            if (count($aStoreInfos) == 4) {
                $eStore->setSubtitle(trim(strip_tags($aStoreInfos[1])));
            }

            $pattern = '#<h2[^>]*>\s*unsere\s*services<\/h2>(.+?)<picture#is';
            if (preg_match($pattern, $page, $serviceMatches)) {
                $pattern = '#<span[^>]*>\s*<h3[^>]*>([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceMatches[1], $matchService)) {
                    $eStore->setService(html_entity_decode(implode(', ', $matchService[1])));
                }
            }

            $pattern = '#ffnungszeiten(.+?)\s*<\/p>#is';
            if (preg_match($pattern, $page, $matchOpenings)) {
                $eStore->setStoreHoursNormalized($matchOpenings[1]);
            }

            $eStore->setStreetAndStreetNumber($aStoreInfos[count($aStoreInfos) - 2])
                ->setZipcodeAndCity($aStoreInfos[count($aStoreInfos) - 1])
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
