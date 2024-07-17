<?php

/* 
 * Store Crawler für Sunpoint (ID: 22388)
 */

class Crawler_Company_Sunpoint_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sunpoint.de/';
        $searchUrl = $baseUrl . 'ueber-sunpoint/studiofinder';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/(studiofinder\/[^"]+?)"#s';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<script[^>]*type="application\/ld\+json"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store info json: ' . $storeDetailUrl);
                continue;
            }

            $jInfos = json_decode($storeInfoMatch[1]);
            $aAddress = preg_split('#\s*,\s*#', $jInfos->address);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*class="info-box"[^>]*>\s*<p[^>]*>[^<]*ffnungszeiten(.+?)</dl#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setAddress($aAddress[0], $aAddress[1])
                ->setPhoneNormalized($jInfos->telephone)
                ->setWebsite($storeDetailUrl)
                ->setLatitude($jInfos->geo->latitude)
                ->setLongitude($jInfos->geo->longitude);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}