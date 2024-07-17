<?php
/**
 * Store Crawler for Mix Markt (ID: 28835)
 */

class Crawler_Company_MixMarkt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.mixmarkt.eu/';
        $searchUrl = $baseUrl . 'de/germany/maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(https:\/\/www\.mixmarkt\.eu\/de\/germany\/maerkte\/\d+\/)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeUrlMatches[1]) as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="description[^"]*description-filial"[^>]*>(.+?)<\/aside>#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $singleStoreUrl);
                continue;
            }

            $pattern = '#Adresse\s*:\s*([^,]+?)\s*,\s*([^<]+)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from info list: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#\/(\d+)\/$#';
            if (preg_match($pattern, $singleStoreUrl, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)Adresse#';
            if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#fon\s*:\s*([^<]+)\s*<#i';
            if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax\s*:\s*([^<]+)\s*<#i';
            if (preg_match($pattern, $infoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#mail\s*:\s*([^<]+)\s*<#i';
            if (preg_match($pattern, $infoListMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}