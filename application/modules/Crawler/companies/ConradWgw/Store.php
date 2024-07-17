<?php
/**
 * Store Crawler für Conrad WGW (ID: 72863)
 */

class Crawler_Company_ConradWgw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.conrad.at/';
        $searchUrl = $baseUrl . 'de/ueber-conrad/partner-shops.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="cmp-text"[^>]*>(.+?ffnungszeiten.+?)<\/div>\s*<\/div>\s*<\/div>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s*[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#tel\s*\.?\s*:?\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)Anfahrt#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#\s*\|\s*#', ' ', $storeHoursMatch[1]));
            }

            $pattern = '#href="[^"]*google[^"]*\/\@([^,]+?),([^,]+?),#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $pattern = '#^\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $titleMatch)) {
                $eStore->setTitle($titleMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2]);

            $cStores->addElement($eStore);
        }

        $searchUrl = $baseUrl . 'de/megastores.html';

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<\/p>\s*<ul[^>]*>(.+?)<\/ul#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get mega store list.');
        }

        $pattern = '#<a[^>]*href="\/(de\/megastores\/[^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any mega stores from list.');
        }

        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl . "\n" . $page);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#tel\s*\.?\s*:?\s*([^<]+?)\s*<br[^>]*>\s*<a[^>]*href="mailto:(filiale[^"]+?)"#i';
            if (preg_match($pattern, $singleStore, $phoneMailMatch)) {
                $eStore->setPhoneNormalized($phoneMailMatch[1])
                    ->setEmail($phoneMailMatch[2]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3])
                ->setWebsite($storeDetailUrl)
                ->setStoreNumber(preg_replace(array('#.+\/([^\/]+)\.html$#', '#scs-voesendorf#'), array('$1', 'wien-scs'), $eStore->getWebsite()));

            $cStores->addElement($eStore);

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}