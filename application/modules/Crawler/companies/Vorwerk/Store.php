<?php

/* 
 * Store Crawler für Vorwerk (ID: 67255)
 */

class Crawler_Company_Vorwerk_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.vorwerk.com';
        $searchUrl = $baseUrl . '/de/de/c/home/produkt-vorfuehrung/stores.storeLocator.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($searchUrl);
        $jsonResponse = $sPage->getPage()->getResponseAsJson();

        if(empty($jsonResponse)) {
            throw new Exception('No response from URL: ' . $searchUrl);
        }

        foreach($jsonResponse->stores as $jsonStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $description = preg_replace(
                '#\[\[NAME]]#',
                $jsonStore->displayName,
                $jsonStore->longDescription
            );

            $eStore->setTitle($jsonStore->displayName)
                ->setText($description)
                ->setStoreNumber($jsonStore->name)
                ->setCity($jsonStore->address->town)
                ->setStreetAndStreetNumber($jsonStore->address->line1)
                ->setZipcode($jsonStore->address->postalCode)
                ->setPhoneNormalized($jsonStore->address->phone)
                ->setLatitude($jsonStore->geoPoint->latitude)
                ->setLongitude($jsonStore->geoPoint->longitude)
                ->setStoreHoursNormalized(
                    $this->normalizeOpeningHours($jsonStore->openingHours->weekDayOpeningList)
                )
            ;

            if($jsonStore->storeImages[0]->url !== 'null') {
                $eStore->setImage($jsonStore->storeImages[0]->url);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function normalizeOpeningHours(array $openingHours)
    {
        $normalizedHours = [];

        foreach($openingHours as $openingHour) {
            if($openingHour->closed) {
                continue;
            }

            switch($openingHour->weekDay) {
                case 'MONDAY':
                    $normalizedHours[] = 'Mo ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
                case 'TUESDAY':
                    $normalizedHours[] = 'Di ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
                case 'WEDNESDAY':
                    $normalizedHours[] = 'Mi ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
                case 'THURSDAY':
                    $normalizedHours[] = 'Do ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
                case 'FRIDAY':
                    $normalizedHours[] = 'Fr ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
                case 'SATURDAY':
                    $normalizedHours[] = 'Sa ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
                case 'SUNDAY':
                    $normalizedHours[] = 'Su ' . $openingHour->openingTime->formattedHour . ' - ' .
                        $openingHour->closingTime->formattedHour;
                    break;
            }
        }

        return implode(', ', $normalizedHours);
    }
}
