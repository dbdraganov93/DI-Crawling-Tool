<?php

/*
 * Store Crawler für Hellweg (ID: 28323)
 */

class Crawler_Company_Hellweg_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = [
            '28323' => 'https://www.hellweg.de/',
            '69602' => 'https://www.baywa-baumarkt.de/',
            '72463' => 'https://www.hellweg.at/'
        ];

        $searchUrl = $baseUrl[$companyId] . 'markt/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#const\s*hellweg_markets\s*=\s*([^<]+?)\s*<#';
        if (!preg_match($pattern, $page, $storeMatch)) {
            throw new Exception($companyId . ': unable to find any stores');
        }

        $jStores = json_decode($storeMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->number)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lon)
                ->setStreetAndStreetNumber($singleJStore->street)
                ->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city);

            if (strlen($singleJStore->url)) {
                $sPage->open($singleJStore->url);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<link[^>]*rel="canonical"[^>]*href="([^"]+?)"#';
                if (preg_match($pattern, $page, $urlMatch)) {
                    $eStore->setWebsite($urlMatch[1]);
                }

                $pattern = '#href="tel:([^"]+?)"#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#Fax\s*:\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $page, $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                }

                $pattern = '#ffnungszeiten\s*<\/strong>(.+?)<\/dl#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
