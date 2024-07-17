<?php

/**
 * Store Crawler für Smyths Toys (ID´s: de->22328, ch->72221)
 */
class Crawler_Company_SmythsToys_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $countries = [
            22328 => 'de/de-de',
            72221 => 'ch/de-ch',
            72501 => 'at/de-at'
        ];

        $baseUrl = 'https://www.smythstoys.com';
        $searchUrl = "$baseUrl/$countries[$companyId]/store-finder?" .
            "q=&" .
            "latitude=51&" .
            "longitude=13";

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($this->getData($searchUrl) as $singleJStore) {

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($singleJStore->line2 ?: end(preg_split('#<br\s*\/>#', $singleJStore->line1)))
//                ->setWebsite($baseUrl . explode('?', $singleJStore->url)[0])   // Website doesnt work. it seems, there are no Store sites, currently
                ->setCity($singleJStore->town)
                ->setZipcode($singleJStore->postalCode)
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setStoreHoursNormalized($this->getOpenings($singleJStore->openings));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @param string $searchUrl
     * @return array
     * @throws Exception
     */
    private function getData(string $searchUrl): array
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $aStores = $sPage->getPage()->getResponseAsJson()->data;

        if (!count($aStores)) {
            throw new Exception('Json doesnt containing Data -> No Stores available');
        }
        return $aStores;
    }

    /**
     * @param stdClass $openings
     * @return string
     */
    private function getOpenings(stdClass $openings): string
    {
        $aTimes = [];
        foreach ($openings as $day => $time) {
            $aTimes[] = $day . ' ' . $time;
        }
        return implode(',', $aTimes);
    }
}