<?php

/**
 * Store Crawler für Hammer Heimtex (ID: 67475)
 */
class Crawler_Company_Hammer_Store extends Crawler_Generic_Company
{
    private const STORE_WEBSITE = 'https://www.hammer-zuhause.de/angebote?utm_source=whatsapp&utm_medium=chat&utm_campaign=ohne&utm_content=angebote';
    private const STORE_TITLE_PREFIX = 'Hammer ';

    public function crawl($companyId)
    {
        $searchUrl = 'https://www.hammer-heimtex.de/maerkte/hammer';

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);

        $existingDistributions = $this->getExistingDistributions($companyId);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sPage->getPage()->getResponseAsJson()->results as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($store->name)
                ->setTitle(self::STORE_TITLE_PREFIX . explode('-', $store->address->town)[0])
                ->setStreet($store->address->line1)
                ->setStreetNumber($store->address->line2)
                ->setCity($store->address->town)
                ->setZipcode($store->address->postalCode)
                ->setWebsite(self::STORE_WEBSITE)
                ->setPhone($store->address->phone)
                ->setEmail($store->address->email)
                ->setFax($store->address->fax)
                ->setLongitude($store->geoPoint->longitude)
                ->setLatitude($store->geoPoint->latitude)
                ->setDistribution($existingDistributions[$store->name] ?: "")
                ->setStoreHoursNormalized($this->getOpenings($store->openingHours->weekDayOpeningList));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @param string $companyId
     * @return array
     */
    private function getExistingDistributions(string $companyId): array
    {
        $sMarktjagdApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = $sMarktjagdApi->findStoresByCompany($companyId);

        $ret = [];
        foreach ($cStores->getElements() as $eStore) {
            $ret[$eStore->storeNumber] = "$eStore->distribution";
        }
        ksort($ret);
        return $ret;
    }

    /**
     * @param object $openingJsons
     * @return string
     */
    private function getOpenings($openingJsons)
    {
        $opening = '';
        $separator = ', ';
        foreach ($openingJsons as $openingJson) {
            $thisDay = $openingJson->weekDay . ' ' . $openingJson->openingTime->formattedHour . ' - ' . $openingJson->closingTime->formattedHour;
            $opening = trim($opening . $separator . $thisDay, $separator);
        }
        return $opening;
    }
}