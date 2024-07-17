<?php

/**
 * Store Crawler für Super Biomarkt (ID: 80001)
 */
class Crawler_Company_SuperBioMarkt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.superbiomarkt.com/';
        $searchUrl = $baseUrl . 'unsere-maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<ul[^>]*id="menu-unsere-maerkte"[^>]*>(.+?)<\/ul#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<li[^>]*>\s*<a[^>]*href="([^"]+?)"[^>]*>\s*([^<]+)\s*<#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            if (preg_match('#bio-to-go#', $singleStoreUrl)) {
                continue;
            }

            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<p[^>]*>\s*([^\|]+?)\s*\|\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)<\/div#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#unser\s*service\s*für\s*sie(.+?)<div[^>]*class="wpb_text_column#i';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<strong[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $strServices = '';
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#zahlung#i', $singleService)) {
                            $eStore->setPayment($singleService);
                            continue;
                        }
                        if (preg_match('#barrierefrei#i', $singleService)) {
                            $eStore->setBarrierFree(TRUE);
                            continue;
                        }

                        if (preg_match('#wc$#i', $singleService)) {
                            $eStore->setToilet(TRUE);
                            continue;
                        }

                        if (preg_match('#parkplätze#i', $singleService)) {
                            $eStore->setParking($singleService);
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

        return $this->getResponse($cStores, $companyId);
    }

}
