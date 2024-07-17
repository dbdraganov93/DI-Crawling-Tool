<?php
/**
 * Store Crawler für IKEA WGW (ID: 73466)
 */

class Crawler_Company_IkeaWgw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.ikea.com/';
        $searchUrl = $baseUrl . 'at/de/stores/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<img[^>]*alt="IKEA\s*Standorte[^>]*>(.+?)<\/ul#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*class="thumbnail-list__link"[^>]*>\s*IKEA\s*(Einrichtungshaus|Kompakt)#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(A\s*-\s*)?(\d{4}\s+[A-ZÄÖÜ][^<]+)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<strong[^>]*>\s*Öffnungszeiten(.+?)<\/p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#<img[^>]*srcset="[^"]*\/(images[^,"]+?xxxl[^",]+)("|,)#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }

            $pattern = '#<h1[^>]*class="page-title__heading"[^>]*>\s*(IKEA\s+[^\s]+?)\s*[A-Z]#';
            if (preg_match($pattern, $page, $titleMatch)) {
                $eStore->setTitle($titleMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}