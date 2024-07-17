<?php
/**
 * Store Crawler für Leader Price FR (ID: 72336)
 */

class Crawler_Company_LeaderPriceFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.leaderprice.fr/';
        $searchUrl = $baseUrl . 'nos-magasins';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="liste-magasins"[^>]*>(.+?)<\/ul>\s*<\/div>\s*<\/div>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/(magasin[^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#data-locations=\'\[([^\]]+?)\]\'#';
            if (!preg_match($pattern, $page, $storeInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }

            $jInfos = json_decode($storeInfoListMatch[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*class="ninja"[^>]*data-code="(\d+)"#';
            if (preg_match($pattern, $page, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $pattern = '#horaires\s*d\'ouverture\s*<\/h3>(.+?)<\/ul#i';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fra');
            }

            $eStore->setWebsite($storeDetailUrl)
                ->setStreetAndStreetNumber(html_entity_decode($jInfos->address, ENT_QUOTES), 'FR')
                ->setZipcode($jInfos->cp)
                ->setCity(html_entity_decode($jInfos->city, ENT_QUOTES))
                ->setPhoneNormalized($jInfos->phone)
                ->setLatitude($jInfos->latitude)
                ->setLongitude($jInfos->longitude);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}