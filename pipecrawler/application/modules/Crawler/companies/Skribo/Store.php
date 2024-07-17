<?php

/**
 * Store Crawler für Skribo (ID: 73653)
 */
class Crawler_Company_Skribo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $url = "https://www.skribo.de";
        $searchUrl = "$url/haendler";

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sPage->getDomElsFromUrlByClass($searchUrl, 'store', 'div', true) as $store) {
            $address = $store->getElementsByTagName('address')[0];
            $openings = $store->getElementsByTagName('dl')[0];
            $storeHours = '';
            foreach ($openings->childNodes as $key => $childNode) {
                $storeHours .= $childNode->textContent . ($key % 2 ? ', ' : ': ');
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($this->getRandomBrochureNumber())
                ->setTitle(trim($store->getElementsByTagName('h2')[0]->textContent))
                ->setWebsite($sPage->getDomElsFromDomEl($store, 'btn', 'class', 'a')[0]->getAttribute('href'))
                ->setLatitude($store->getAttribute('data-lat'))
                ->setLongitude($store->getAttribute('data-lng'))
                ->setStreetAndStreetNumber($address->getElementsByTagName('p')[0]->childNodes[0]->textContent)
                ->setZipcodeAndCity($address->getElementsByTagName('p')[0]->childNodes[2]->textContent)
                ->setPhoneNormalized($address->getElementsByTagName('a')[0]->textContent)
                ->setEmail($address->getElementsByTagName('a')[$address->getElementsByTagName('a')->length - 1]->textContent);

            if ($storeHours) {
                $eStore->setStoreHoursNormalized($storeHours);
            }

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}


