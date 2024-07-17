<?php
/**
 * Store Crawler für NatureO FR (ID: 73504)
 */

class Crawler_Company_NatureOFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.natureo-bio.fr/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=natureo_search_stores&posts_per_page=100&page=1&radius=1000&lat=48.856614&lng=2.3522219999999834&geosearch=&services=&producteurs_locaux=false&producers_natureo=false&start_position=1';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*(data-store-id=.+?)<\\\/div>\s*\\\n\s*<\\\/div>\s*\\\n\s*<\\\/li>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#data-store-id=\\\"(\d+)\\\"#';
            if (!preg_match($pattern, $singleStore, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number: ' . $singleStore);
                continue;
            }

            $pattern = '#<span[^>]*class=\\\"wpsl-street\\\"[^>]*>\s*([^<]+?)\s*<\\\/span>\s*\\\n\s*(<span[^>]*class=\\\"wpsl-street\\\"[^>]*>\s*[^<]*\s*<\\\/span>\s*\\\n\s*)?\s*<span[^>]*>\s*(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#href=\\\"tel:([^\\\]+)#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<a[^>]*href=\\\"([^"]+?)\\\"[^>]*class=\\\"btn[^\\\]*btn-block#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite(preg_replace('#\\\#', '', $websiteMatch[1]));
            }

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*class="store-hours-container"[^>]*>(.+?)<\/table#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'FR');
                }

                $pattern = '#les\s*services(.+?)<\/ul#i';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#<li[^>]*>\s*<img[^>]*>\s*(.+?)\s*<\/li#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                }

                $pattern = '#"locations":([^\]]+?\}\])\};#';
                if (preg_match($pattern, $page, $locationMatch)) {
                    $eStore->setLatitude(json_decode($locationMatch[1])[0]->lat)
                        ->setLongitude(json_decode($locationMatch[1])[0]->lng);
                }
            }

            $eStore->setStoreNumber($storeNumberMatch[1])
                ->setAddress(html_entity_decode(preg_replace("#\\\u([0-9a-f]{4})#i", "&#x\\1;", $addressMatch[1]), ENT_NOQUOTES, 'UTF-8'), html_entity_decode(preg_replace("#\\\u([0-9a-f]{4})#i", "&#x\\1;", $addressMatch[3]), ENT_NOQUOTES, 'UTF-8'), 'FR');

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}