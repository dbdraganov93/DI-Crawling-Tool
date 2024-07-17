<?php
/**
 * Store Crawler für Intersport FR (ID: )
 */

class Crawler_Company_IntersportFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.intersport.fr/';
        $searchUrl = $baseUrl . 'store-finder/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*locationsMap\s*=\s*(\[[^\]]+?\])#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#"link"\s*:\s*"\/([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $aDetails = preg_split('#\/#', $singleStoreUrl);
            foreach ($aDetails as &$singleDetail) {
                $singleDetail = urlencode($singleDetail);
            }
            $singleStoreUrl = implode('/', $aDetails);

            $storeDetailUrl = $baseUrl . $singleStoreUrl . '/';

            try {
                $ch = curl_init($storeDetailUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, TRUE);
                $page = preg_replace(array('#\s{2,}#', '#\n#', '#\t#', '#\x{00a0}#siu'), ' ', html_entity_decode(curl_exec($ch)));
                $pageInfo = curl_getinfo($ch);
                curl_close($ch);

                $pattern = '#intersport-rent#';
                if (preg_match($pattern, $pageInfo['url'])) {
                    continue;
                }

                $pattern = '#<p[^>]*class="adresse"[^>]*>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[^<]+?)\s*<#s';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->info($companyId . ': unable to get store address: ' . $storeDetailUrl);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<div[^>]*class="horaires"[^>]*>(.+?)<\/div#s';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fr');
                }

                $pattern = '#phone\s*:?\s*<[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#"latitude"\s*:\s*"([^"]+?)",\s*"longitude"\s*:\s*"([^"]+?)"#';
                if (preg_match($pattern, $page, $geoMatch)) {
                    $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
                }

                $eStore->setAddress($addressMatch[1], $addressMatch[2], 'fr')
                    ->setWebsite($storeDetailUrl);

                $cStores->addElement($eStore);
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': invalid uri supplied: ' . $storeDetailUrl);
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}