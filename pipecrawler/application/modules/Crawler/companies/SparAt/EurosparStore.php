<?php

/**
 * Store Crawler für EUROSPAR AT (ID: 73049, STAGE ID: 76259)
 */

class Crawler_Company_SparAt_EurosparStore extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.spar.at';
        $searchUrl = $baseUrl . '/standorte/_jcr_content.stores.v2.html?filter=EUROSPAR';
        $stores = $this->getStores($searchUrl);

        if (empty($stores)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($stores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $openingHours = [];
            $day = [];
            foreach ($singleJStore->shopHours as $time) {
                $day[] = $time->openingHours->dayType;

                foreach ($day as $singleDay) {
                    if (empty($time->openingHours->from1->hourOfDay) && empty($time->openingHours->to1->hourOfDay)) {
                        continue;
                    }

                    $openingHours[] = $singleDay . ' ' . $time->openingHours->from1->hourOfDay . ':' . $time->openingHours->from1->minute
                        . ' - ' . $time->openingHours->to1->hourOfDay . ":" . $time->openingHours->from1->minute;
                }
            }

            $eStore->setStoreNumber($singleJStore->locationId)
                ->setTitle($singleJStore->plantType->name)
                ->setStreetAndStreetNumber($singleJStore->address)
                ->setZipcode($singleJStore->zipCode)
                ->setCity($singleJStore->city)
                ->setStoreHoursNormalized(implode(', ', $openingHours))
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setWebsite($baseUrl . $singleJStore->pageUrl)
                ->setPhone(str_replace('/', ' ', $singleJStore->telephone))
                ->setEmail($singleJStore->email);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId, 2, false);
    }

    private function getStores($searchUrl): ?array
    {
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);

        $stores = json_decode($result);

        if (empty($stores)) {
            $this->_logger->warn(
                'No stores found to download. Please, check the resource link'
            );
        }
        return $stores;
    }
}
