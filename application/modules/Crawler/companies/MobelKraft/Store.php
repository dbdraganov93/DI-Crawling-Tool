<?php

/*
 * Store Crawler für Möbel Kraft (ID: 73555)
 */

class Crawler_Company_MobelKraft_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.moebel-kraft.de';
        $searchUrl = $baseUrl . '/standorte';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-flags="(.+)"[^>]*data-image-folder#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleJStore->abbreviation)
                ->setLatitude($singleJStore->mapCoordinates->lat)
                ->setLongitude($singleJStore->mapCoordinates->lng)
                ->setStoreHoursNormalized($singleJStore->openingTimes)
                ->setWebsite($baseUrl . $singleJStore->path);

            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<br[^>]*>\s*(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address ' . $eStore->getWebsite());
                continue;
            }

            $pattern = '#storeContactEntry__phoneNr[^>]*storeContactEntry__phoneNr--phoneNrBig"[^>]*href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($addressMatch[1])
                ->setZipcodeAndCity($addressMatch[2]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

}
