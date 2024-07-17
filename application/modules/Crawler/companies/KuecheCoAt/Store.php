<?php

/**
 * Store Crawler for Küche&Co AT (ID: 72509)
 */

class Crawler_Company_KuecheCoAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.kuecheco.at/';
        $searchUrl = $baseUrl . 'studios/suche/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<section[^>]*class="studio-teaser"[^>]*>(.+?)<\/section>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*>\s*Zum\s*Studio#';
            if (preg_match($pattern, $page, $websiteMatch)) {
                $eStore->setWebsite($baseUrl . $websiteMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2]);

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<time[^>]*itemprop="openingHours"[^>]*datetime="([^"]+?)"#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                $pattern = '#itemprop="telephone"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#itemprop="faxNumber"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                }

                $pattern = '#itemprop="email"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }

                $pattern = '#<h2[^>]*class="service-card__headline\s*icon-service[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $page, $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}