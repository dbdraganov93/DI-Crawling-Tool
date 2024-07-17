<?php

/**
 * Store Crawler für IKEA (ID: 61)
 */
class Crawler_Company_Ikea_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://standorte.ikea.de/';
        $storeUrl = $baseUrl . 'splashpage/eh';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $realUrl = $sPage->getRedirectedUrl($storeUrl);
        $sPage->open($realUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#[href|src]="((https:\/\/www.ikea.com)?\/de\/de\/stores\/[^"\/]+?)\/#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any store links');
        }

        foreach ($storeLinkMatches[1] as $storeLink) {
            if (!preg_match('#^https#', $storeLink)) {
                $storeLink = 'https://www.ikea.com' . $storeLink;
            }

            $sPage->open($storeLink);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err("Store lost $storeLink");
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#div[^>]*id="openinghours"[^>]*>(.+?)<\/table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2])
//                ->setWebsite('https://track.adform.net/C/?bn=38558238')
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
