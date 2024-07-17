<?php
/**
 * Store Crawler für Conforama FR (ID: 72326)
 */

class Crawler_Company_ConforamaFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.conforama.fr/';
        $searchUrl = $baseUrl . 'liste-des-magasins/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/(magasins-conforama[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . preg_replace(array('#\s+#', '#\/%20B#'), array('%20', '%20/%20B'), $singleStoreUrl);

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<\/h[^>]*>\s*<div[^>]*>\s*<p[^>]*>\s*([^<]+?)\s*<br[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*</p#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*class="list-horaires"[^>]*>(.+?)</ul#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace(array('#(\d)\s*<\/span>\s*<span[^>]*>\s*(\d+)#', '#>\s*([A-Z])#', '#([a-z])<#'), array('$1,$2', '>, $1', '$1 <'), $storeHoursMatch[1]), 'text', TRUE, 'fra');
            }

            $pattern = '#<span[^>]*class="tel-depot"[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<div[^>]*class="awk-services-dispo[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<img[^>]*alt="([^"]+?)"#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }

            $pattern = '#lat=([^\&]+?)\&long=([^\&]+?)\&#';
            if (preg_match($pattern, $storeDetailUrl, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
