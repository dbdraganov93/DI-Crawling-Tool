<?php

/* 
 * Store Crawler für BabyOne (ID: 28698)
 */

class Crawler_Company_BabyOne_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.babyone.de';
        $searchUrl = $baseUrl . '/Fachm%C3%A4rkte';
        $sPage = new Marktjagd_Service_Input_Page();

        $aCampaignZipcodes = [45894, 44805, 45141, 45143, 45472, 46049, 44805, 42283, 47803, 40878, 42283, 41066, 41564, 50829, 50997, 50171, 53347];

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="([^"]+?StoreID=[^"]+)"#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception($companyId . ': could not get store urls from ' . $searchUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sMatches[1] as $storeUrl) {
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setWebsite($storeUrl);

            if (preg_match('#StoreID=(.+)$#', $storeUrl, $match)) {
                $eStore->setStoreNumber($match[1]);
            }

            if (preg_match('#"openingHours":\s*"([^"]+)"#is', $page, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            if (preg_match('#"telephone":\s*"([^"]+)"#is', $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            if (preg_match('#"streetAddress":\s*"([^"]+)"#is', $page, $match)) {
                $eStore->setStreetAndStreetNumber($match[1]);
            }

            if (preg_match('#"addressLocality":\s*"([^"]+)"#is', $page, $match)) {
                $eStore->setCity($match[1]);
            }

            if (preg_match('#([0-9]{5})\s*' . $eStore->getCity() . '#', $page, $match)) {
                $eStore->setZipcode($match[1]);
            } elseif (preg_match('#kehl\sam\srhein#i', $eStore->getCity())) {
                $eStore->setZipcode('77694');
            }

            if (preg_match('#>Fax[^\s]+([^<]+)<#', $page, $match)) {
                $eStore->setFaxNormalized($match[1]);
            }

            if (preg_match_all('#<span[^>]*class="jsStoreBenefit"[^>]*>(.+?)</span>#', $page, $match)) {
                $eStore->setService(implode(', ', $match[1]));
            }

            if (in_array($eStore->getZipcode(), $aCampaignZipcodes)) {
                $eStore->setDistribution('Kampagne');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
