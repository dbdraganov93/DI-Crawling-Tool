<?php
/**
 * Store Crawler für Casino FR (ID: 72328)
 */

class Crawler_Company_CasinoFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://magasins.supercasino.fr';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h2[^>]*CitiesLinks[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
        if (!preg_match($pattern, $page, $cityListMatch)) {
            throw new Exception($companyId . ': unable to get city link list.');
        }

        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $cityListMatch[1], $cityMatches)) {
            throw new Exception($companyId . ': unable to get city links from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($cityMatches[1] as $singleCity) {
            $sPage->open($singleCity);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<li[^>]*class="ItemMagasin[^>]*>(.+?)<\/li#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no stores for ' . $singleCity);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStore);
                    continue;
                }

                $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<a[^>]*class="Button\s*ButtonFull[^>]*href="([^"]+?\/([^\/"]+?))"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $eStore->setWebsite($urlMatch[1])
                        ->setStoreNumber($urlMatch[2]);
                }

                $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                    ->setZipcode($aInfos['postalCode'])
                    ->setCity(ucwords(strtolower($aInfos['addressLocality'])))
                    ->setPhoneNormalized($aInfos['telephone']);

                if (strlen($eStore->getWebsite())) {
                    $sPage->open($eStore->getWebsite());
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#itemprop="openingHours"[^>]*content="([^"]+?)"#';
                    if (preg_match($pattern, $page, $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'eng');
                    }

                    $pattern = '#var\s*latitude\s*=\s*([^;]+?)\s*;\s*var\s*longitude\s*=\s*([^;]+?)\s*;#';
                    if (preg_match($pattern, $page, $geoMatch)) {
                        $eStore->setLatitude($geoMatch[1])
                            ->setLongitude($geoMatch[2]);
                    }

                    $pattern = '#<ul[^>]*class="Listing_Offres_Et_Services[^>]*>(.+?)<\/ul>#';
                    if (preg_match($pattern, $page, $serviceListMatch)) {
                        $pattern = '#<span[^>]*class="ContentText"[^>]*>\s*([^<]+?)\s*<#';
                        if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                            $eStore->setService(implode(', ', $serviceMatches[1]));
                        }
                    }
                }

                $cStores->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }
}
