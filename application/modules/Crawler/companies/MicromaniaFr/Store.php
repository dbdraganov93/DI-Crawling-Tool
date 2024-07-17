<?php
/**
 * Store Crawler für Micromania FR (ID: 72413)
 */

class Crawler_Company_MicromaniaFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.micromania.fr/';
        $searchUrl = $baseUrl . 'liste-magasins-micromania.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="full-list"[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="\s*(https?:\/\/www\.micromania\.fr\/magasin\/\d+\/[^"]+?)\s*"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $storeDetailUrl) {
            $ch = curl_init($storeDetailUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            $page = curl_exec($ch);
            curl_close($ch);

            $pattern = '#<li[^>]*class="address"[^>]*>([^<]+?<br[^>]*>)?\s*([^<]+?)\s*<br[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#\/(\d+)\/#';
            if (!preg_match($pattern, $storeDetailUrl, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<ul[^>]*id="shopHour"[^>]*>(.+?)<\/ul#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'FR');
            }

            $eStore->setAddress($addressMatch[2], $addressMatch[3], 'FR')
                ->setStoreNumber($storeNumberMatch[1])
                ->setWebsite($storeDetailUrl);

            if (strlen($addressMatch[1])) {
                $eStore->setSubtitle(trim(strip_tags($addressMatch[1])));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}