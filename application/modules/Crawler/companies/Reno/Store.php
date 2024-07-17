<?php

/**
 * Storecrawler für Reno (ID: 336)
 */
class Crawler_Company_Reno_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://rest.reno.de/wcs/resources/store/10151/storelocator/latitude/51.0579575/longitude/13.7356578?radius=800&siteLevelStoreSearch=true&maxItems=9999';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($baseUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        if (!count($jStores->PhysicalStore) > 0) {
            $this->_logger->err('Company ID- ' .  $companyId . ': Unable to get json response for store list.');
            exit;
        }

        foreach ($jStores->PhysicalStore as $singleStore) {
            if (strcmp($singleStore->country, 'DE') !== 0) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setZipcode(trim($singleStore->postalCode))
                    ->setCity($singleStore->city)
                    ->setLatitude($singleStore->latitude)
                    ->setLongitude($singleStore->longitude)
                    ->setStoreNumber($singleStore->uniqueID)
                    ->setPhoneNormalized($singleStore->telephone1)
                    ->setStreetAndStreetNumber( $singleStore->addressLine[0]);
            
            $storeHours = '';
            foreach ($singleStore->Attribute as $attribute) {
                if ($attribute->name == 'emailAddress') {
                    $eStore->setEmail($attribute->value);
                    continue;
                }

                if (substr($attribute->name, 0, 7) != 'openHrs') {
                    continue;
                }

                if (strlen($storeHours)) {
                    $storeHours .= ', ';
                }
                $storeHours .= preg_replace('#.+?\_(\w{2})\w$#', '$1', $attribute->name) . ' ' . $attribute->value;

            }

            $eStore->setStoreHoursNormalized($storeHours);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
