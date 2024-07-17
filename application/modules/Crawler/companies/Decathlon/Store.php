<?php

/* 
 * Store Crawler für Decathlon (ID: 68079)
 */

class Crawler_Company_Decathlon_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseURL = 'https://www.decathlon.de';
        $searchUrl = $baseURL . '/store-locator';
        $sPage = new Marktjagd_Service_Input_Page();

        $storeElements = $sPage->getDomElsFromUrlByClass($searchUrl, "name svelte-spacze");

        if (!$storeElements) {
            $this->_logger->crit("Couldn't find matching DOMElements for stores from $searchUrl");
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeElements as $singleStore) {
            $storeLink = $baseURL . $singleStore->getAttribute('href');
            // invisible Filiale Dortmund Warenhaus rauswerfen
            if ($storeLink == 'https://www.decathlon.de/store-view/filiale-dortmund-0070159001590') {
                continue;
            }
            // regex gibt die in der url encodierte storeid zurueck Format: 0070ID0ID
            if (!preg_match('#\d+0(\d{4})$#', $storeLink, $apiID)) {
                $this->metaLog("StoreID konnte nicht extrahiert werden", "err");
                continue;
            }
            $apiUrl = "https://api.woosmap.com/stores/search/?key=woos-77d134ba-bbcc-308d-959e-9a8ce014dfec&query=idstore%3A%22${apiID[0]}%22";

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Origin: https://www.decathlon.de'
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $payload = json_decode($response);
            $pageSub = $payload->features[0]->properties;
            $hours = array();
            $days = array('So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa');
            for ($i = 1; $i < 7; $i++) {
                $start = $pageSub->weekly_opening->{'1'}->hours[0]->start;
                $end = $pageSub->weekly_opening->{'1'}->hours[0]->end;

                array_push($hours, "${days[$i]}: ${start} - ${end}");
            }
            $aCityInfos = preg_split('#\s+-\s+#', $pageSub->name);

            $sGeo = new Marktjagd_Database_Service_GeoRegion();
            $distribution = $sGeo->findRegionByZipCode(trim($pageSub->address->zipcode));

            $eStore = new Marktjagd_Entity_Api_Store();

            // Hardcoded removal of Theresienhöhe 5, München, 80339 -> StoreNumber = 2062
            if (preg_match('#Theresienhöhe#', $pageSub->address->lines[0]) ||
                preg_match('#2062#', $apiID[1])
            ) {
                continue;
            }

            $eStore->setStoreNumber($apiID[1])
                ->setPhoneNormalized($pageSub->contact->phone)
                ->setCity($aCityInfos[0])
                ->setStreetAndStreetNumber($pageSub->address->lines[0])
                ->setZipcode(trim($pageSub->address->zipcode))
                ->setStoreHoursNormalized(implode(',', $hours))
                ->setDistribution($distribution);

            if (array_key_exists(1, $aCityInfos)) {
                $eStore->setStoreHoursNotes($aCityInfos[1]);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
