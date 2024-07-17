<?php

/*
 * Store Crawler für Porta (ID: 108)
 */

class Crawler_Company_Porta_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://porta.de/';
        $searchUrl = $baseUrl . 'einrichtungshaeuser';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(https://porta\.de)?\/(einrichtungshaeuser\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeNameMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $storeNameMatches[2] = array_unique($storeNameMatches[2]);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeNameMatches[2] as $singleStoreName) {
            $storeDetailUrl = $baseUrl . trim($singleStoreName);

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $pattern = '#"mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#<div[^>]*id="tab-store-services"[^>]*>(.+?)</div#s';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<li[^>]*>\s*<[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $strServices = '';
                    $strPayment = '';
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#Finanzierung#', $singleService)) {
                            if (strlen($strPayment)) {
                                $strPayment .= ', ';
                            }
                            $strPayment .= $singleService;
                            continue;
                        }

                        if (preg_match('#parken#i', $singleService)) {
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

            $pattern = '#itemprop="openingHours"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }

            $pattern = '#<img[^>]*src="\/([^"]+?)"[^>]*alt="' .  $eStore->getCity() . '"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                    ->setZipcode($aInfos['postalCode'])
                    ->setCity($aInfos['addressLocality'])
                    ->setPhoneNormalized($aInfos['telephone'])
                    ->setWebsite($storeDetailUrl)
                    ->setService($strServices)
                    ->setPayment($strPayment);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
