<?php

/**
 * Store Crawler für Netto Supermarkt (ID: 73)
 */
class Crawler_Company_NettoSupermarkt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://netto.de/';
        $searchUrl = $baseUrl . 'umbraco/api/StoresData/StoresV2';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStoresApi = $sApi->findStoresByCompany($companyId);

        $aMapStores = array();
        foreach ($cStoresApi->getElements() as $eStoreApi) {
            $aMapStores[$eStoreApi->getZipcode()] = $eStoreApi->getDistribution();
        }

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#DE#', $singleJStore->address->country)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setCity($singleJStore->address->city)
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->zip)
                ->setLongitude($singleJStore->coordinates[0])
                ->setLatitude($singleJStore->coordinates[1]);

            if (strlen($singleJStore->url)) {
                $eStore->setWebsite($baseUrl . trim($singleJStore->url, '/'));
            }

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<script[^>]*type=\"application\/ld\+json\"[^>]*>\s*([^<]+?)\s*<\/script#';
                if (!preg_match($pattern, $page, $infoMatch)) {
                    $this->_logger->err($companyId . ': unable to get store infos: ' . $eStore->getWebsite());
                }

                $jInfos = json_decode($infoMatch[1]);

                $strTimes = '';
                foreach ($jInfos->openingHoursSpecification as $singleDay) {
                    if (!property_exists($singleDay, 'opens')) {
                        continue;
                    }
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= date('D', strtotime($singleDay->validFrom)) . ' ' . $singleDay->opens . '-' . $singleDay->closes;
                }

                $eStore->setStoreHoursNormalized($strTimes);
            }

            // Ermitteln des Vertriebsbereiches
            if (array_key_exists($eStore->getZipcode(), $aMapStores)) {
                $eStore->setDistribution($aMapStores[$eStore->getZipcode()]);
            } else {
                $this->_logger->info($companyId . ': no distribution found for ' . $eStore->getZipcode() . '-' . $eStore->getCity());
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}