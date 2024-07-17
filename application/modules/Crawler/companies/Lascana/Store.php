<?php

/* 
 * Store Crawler für Lascana (ID: 72085)
 */

class Crawler_Company_Lascana_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.lascana.de/';
        $searchUrl = $baseUrl . 'storefinder/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-markerimageurl\s*=\s*\'https:\/\/www\.lascana\.de\/out\/responsive\/img\/icon_filiale\.gif\'[^\']*data-markerfile\s*=\s*\'([^\']+?)\'#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<address[^>]*>\s*(<p[^>]*>\s*([^<]+?)\s*<\/p>)?\s*<p[^>]*>\s*([^,]+?)\s*,\s*(\d{5}\s*\w+[^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#tel\.?\:?\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#class="button"[^>]*href="([^"]+?)"[^>]*>Mehr\s*Infos#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite($websiteMatch[1]);
            }

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#ffnungszeiten\s*\:?\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                $pattern = '#<br[^>]*>\s*Sortiment\s*\:\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $page, $sectionMatch)) {
                    $eStore->setSection($sectionMatch[1]);
                }
            }

            $eStore->setAddress($addressMatch[3], $addressMatch[4]);

            if (strlen($addressMatch[2])) {
                $eStore->setTitle($addressMatch[2]);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}