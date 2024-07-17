<?php

/**
 * Store Crawler for Maximarkt AT (ID: 72499)
 */

class Crawler_Company_MaxiMarktAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.maximarkt.at/';
        $searchUrl = $baseUrl . 'standorte';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*class="link-more"[^>]*>\s*mehr\s*infos\s*zum\s*standort#i';
        if (!preg_match_all($pattern, $page, $storeDetailUrlMatches)) {
            throw new Exception($companyId . ': unable to find any store detail urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeDetailUrlMatches[1] as $singleStoreDetailUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreDetailUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Kontaktdaten\s*<\/h4>\s*<p[^>]*>\s*(.+?)\s*<\/p>#';
            if (!preg_match($pattern, $page, $addressListMatch)) {
                $this->_logger->err($companyId . ': unable to get store address list: ' . $storeDetailUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<[^>]*>\s*#', $addressListMatch[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten\s*Markt(.+?)<\/ul#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#tel([^<]+?)<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<ul[^>]*class="list--services"[^>]*>(.+?)<\/ul>#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<span[^>]*>\s*([^<]+)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }

            $eStore->setAddress($aAddress[1], $aAddress[2], 'AT')
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}