<?php

/**
 * Store Crawler für REPO-Markt (ID: 28830)
 */
class Crawler_Company_Repo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://repo-markt.de/';
        $searchUrl = $baseUrl . 'standorte-beilagen-online/alle-standorte/';

        $page = $this->curlPage($searchUrl);

        $pattern = '#<li[^>]*>\s*<a[^>]*href="(standorte\-beilagen\-online\/(?!a)[A-z]*\-*[A-z]*\/)"#';
        if (!preg_match_all($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get county list.');
        }

        $aStoreUrls = array();

        foreach ($storeListMatch[1] as $singleCounty) {
            $page = $this->curlPage($baseUrl . $singleCounty);

            $pattern = '#<br \/>\s+<a[^>]*href="([^"]+?\/showFiliale\/[^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from ' . $singleCounty);
                continue;
            }

            $aStoreUrls = array_merge($aStoreUrls, $storeMatches[1]);
        }


        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreUrls as $singleStoreUrl) {
            $completeUrl = $baseUrl . $singleStoreUrl;
            $page = $this->curlPage($completeUrl);

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#is';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();


            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                ->setWebsite($completeUrl)
                ->setStoreHoursNormalized($this->getStoreHours($completeUrl));

            $cStores->addElement($eStore);
        }



        return $this->getResponse($cStores, $companyId);
    }

    private function curlPage($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $page = curl_exec($ch);
        curl_close($ch);
        return $page;
    }

    private function getStoreHours($url)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $storeHours = $sPage->getDomElsFromUrlByClass($url, "bodytext");
        $length = count($storeHours);
        for ($i = 0; $i < $length; $i++) {
            if (preg_match("#ffnungs#", $storeHours[$i]->textContent)) {
                $str = html_entity_decode($storeHours[$i + 1]->textContent);
                $str =  preg_replace('/[^a-z|\s+|^\d|^:-]+/i', '',$str);
                $str =  preg_replace("#(\d)\s(\d\d)#", "$1:$2", $str);
                return $str;
            }
        }
        return "";
    }




}