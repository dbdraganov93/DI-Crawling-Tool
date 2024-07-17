<?php

/**
 * Storecrawler für Möbel Boss (ID: 66)
 *
 */
class Crawler_Company_MoebelBoss_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://moebel-boss.de';
        $searchUrl = $baseUrl . '/moebelhaeuser';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<div[^>]*data-store-data\s*=\s*\'([^\']+)\'#is', $page, $dataMatch)) {
            $this->_logger->info('store stores found for ' . $searchUrl);
        }

        $json = json_decode($dataMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($json as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setPhoneNormalized($jStore->phone)
                    ->setFaxNormalized($jStore->fax)
                    ->setZipcode($jStore->zipCode)
                    ->setStreetAndStreetNumber($jStore->street)
                    ->setEmail($jStore->contactMail)
                    ->setCity($jStore->cityName)
                    ->setLongitude($jStore->longitude)
                    ->setLatitude($jStore->latitude)
                    ->setWebsite($baseUrl . $jStore->storeLink);

            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            if (preg_match('#<div[^>]*class="mod-shop-info__hours"[^>]*>(.+?)</div>#is', $page, $hoursMatch)) {
                $eStore->setStoreHoursNormalized($hoursMatch[1]);
            }

            if (preg_match('#<div[^>]*id="tab-store-services"[^>]*>(.+?)</div>#', $page, $serviceMatch)) {
                if (preg_match_all('#>\s*([^<]+)</li>#', $serviceMatch[1], $serviceList)) {
                    $strService = '';
                    foreach ($serviceList[1] as $service) {
                        if (preg_match('#parken#is', $service)) {
                            $eStore->setParking($service);
                            continue;
                        }

                        if (preg_match('#finanz#is', $service)) {
                            $eStore->setPayment($service);
                            continue;
                        }

                        if (strlen($strService)) {
                            $strService .= ', ';
                        }
                        $strService .= $service;
                    }
                }
            }

            $eStore->setService($strService);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
