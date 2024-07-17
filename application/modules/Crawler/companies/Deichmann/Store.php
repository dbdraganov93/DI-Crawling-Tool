<?php
/**
 * Store Crawler für Deichmann (ID: 341)
 */

class Crawler_Company_Deichmann_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $storeListResponse = $this->safeHttpRequest('https://stores.deichmann.com/assets/locations.json');
        if ($storeListResponse == NULL) {
            throw new Exception("Was not able to get basic asset for crawler");
        }
        $stores = $storeListResponse->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($stores as $store) {
            $storeUrl = 'https://stores.deichmann.com/' . $store->url;
            $eStore = new Marktjagd_Entity_Api_Store();
            $storeResponse = $this->safeHttpRequest($storeUrl);
            if ($storeResponse == NULL) {
                $this->_logger->err($companyId . ': unable to get json information for: ' . $storeUrl);
                continue;
            }

            $page = $storeResponse->getResponseBody();
            $pattern = '#<script[^>]*type\s*=\s*\'application\/ld\+json\'[^>]*>\s*(.+?)\s*<\/script#';
            if (!preg_match($pattern, $page, $jInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get json information for: ' . $storeUrl);
                continue;
            }

            $jInfos = json_decode($jInfoMatch[1]);

            $eStore->setLatitude($store->lat)
                ->setLongitude($store->lng)
                ->setPhoneNormalized($jInfos->address->telephone)
                ->setStreetAndStreetNumber($jInfos->address->streetAddress)
                ->setCity($jInfos->address->addressLocality)
                ->setZipcode($jInfos->address->postalCode)
                ->setStoreHoursNormalized($jInfos->openingHours)
                ->setStoreHoursNotes('Bitte beachten, dass sich die jeweiligen Öffnungs-Regeln der einzelnen Filialen nach den lokalen Inzidenzwerten richten und sich täglich verändern können.')
                ->setWebsite($storeUrl);

            if (preg_match('#\/(mecklenburg-vorpommern|hamburg|schleswig-holstein)\/#i', $eStore->getWebsite())) {
                $eStore->setDistribution('Kampagne');
            } elseif (preg_match('#\/(berlin|brandenburg)\/#i', $eStore->getWebsite())) {
                $eStore->setDistribution('Kampagne_2');
            } elseif (preg_match('#\/(nordrhein-westfalen)\/#i', $eStore->getWebsite())) {
                $eStore->setDistribution('Kampagne_NRW');
            } elseif (preg_match('#\/(hessen|rheinland-pfalz|saarland|bremen|niedersachsen|sachsen-anhalt)\/#i', $eStore->getWebsite())) {
                $eStore->setDistribution('Kampagne_BHNSRPSSA');
            } elseif (preg_match('#\/(bayern|baden-wuerttemberg)\/#i', $eStore->getWebsite())) {
                $eStore->setDistribution('Kampagne_BWB');
            } elseif (preg_match('#\/(thueringen|sachsen)\/#i', $eStore->getWebsite())) {
                $eStore->setDistribution('Kampagne_ST');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

    private function safeHttpRequest($url, $sleep = 1, $maxAttempts = 5)
    {
        $response = NULL;
        $sPage = new Marktjagd_Service_Input_Page();
        sleep($sleep / 2);
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $this->_logger->info("Requesting url: $url");
                $sPage->open($url);
                $response = $sPage->getPage();
                break;
            } catch (Exception $e) {
                $this->_logger->info("Attempt: " . $i + 1 . "/$maxAttempts - Error during http request of page: $url");
                sleep($sleep);
            }
        }
        if ($response == NULL) {
            $this->_logger->info("Was not able to request url: $url");
        }
        return $response;
    }
}

