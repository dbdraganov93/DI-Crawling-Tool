<?php

/**
 * Store Crawler für Baby One AT (ID: 73170)
 */

class Crawler_Company_BabyOneAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.babyone.at/';
        $searchUrl = $baseUrl . 'Fachm%C3%A4rkte';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(https:\/\/www\.babyone\.at\/Fachmarkt\?StoreID=\d+)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<script[^>]*type="application\/ld\+json"[^>]*>(.+?)<\/script>#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list.');
                continue;
            }

            $jInfos = json_decode($infoListMatch[1]);

            $pattern = '#,\s*(\d{4})\s*[A-Z][^<]+\s*<#';
            if (!preg_match($pattern, $page, $zipcodeMatch)) {
                $this->_logger->err($companyId . ': unable to get store zipcode.');
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreHoursNormalized($jInfos->openingHours)
                ->setPhoneNormalized($jInfos->telephone)
                ->setStreetAndStreetNumber($jInfos->address->streetAddress)
                ->setCity($jInfos->address->addressLocality)
                ->setZipcode($zipcodeMatch[1])
                ->setImage($jInfos->image)
                ->setWebsite($jInfos->url);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}