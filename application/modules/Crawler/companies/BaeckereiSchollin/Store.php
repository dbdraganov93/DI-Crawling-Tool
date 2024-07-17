<?php
/**
 * Store Crawler für Bäckerei Schollin (ID: 71553)
 */

class Crawler_Company_BaeckereiSchollin_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://schollin.de/';
        $searchUrl = $baseUrl . 'backereien/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="('.$searchUrl.'[^"]+?)"[^>]*title="Read[^>]*more"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#single-item-address"[^>]*>\s*([^<]+?)\s+(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<br[^>]*>\s*Tel\s*\.?\s*:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#ffnet(.+?)<\/tbody#is';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $strServices = '';
            $pattern = '#single-item-page-filters[^>]*>(.+?)<\/div#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<\/span>\s*([^<]*)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#barrierefrei#i', $singleService)) {
                            $eStore->setBarrierFree(1);
                            continue;
                        }

                        if (preg_match('#zahl#i', $singleService)) {
                            $eStore->setPayment($singleService);
                            continue;
                        }

                        if (preg_match('#park#i', $singleService)) {
                            $eStore->setParking(1);
                            continue;
                        }

                        if (preg_match('#toilette#i', $singleService)) {
                            $eStore->setToilet(1);
                            continue;
                        }

                        if (strlen($strServices)) {
                            $strServices .= ', ';
                        }
                        $strServices .= $singleService;
                    }
                }
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                ->setWebsite($singleStoreUrl)
                ->setService($strServices);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}