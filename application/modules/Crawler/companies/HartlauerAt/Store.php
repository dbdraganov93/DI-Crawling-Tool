<?php
/**
 * Store Crawler für Hartlauer AT (ID: 73468)
 */

class Crawler_Company_HartlauerAt_Store extends Crawler_Generic_Company
{
    /**
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hartlauer.at/on/demandware.store/Sites-Hartlauer-Site/de_AT/Stores-FindStores';
        $sPage   = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $encoder = new Marktjagd_Service_Text_Encoding();

        $sPage->open($baseUrl . '?radius=2000');
        $page = $sPage->getPage()->getResponseBody();

        // Cut out and fix "locations" Json key, It's not a valid json_decode() string
        $explodeJson = explode('"locations":', $page);
        $firstPart   = substr($explodeJson[0], 0, -2);
        $decodedJson = json_decode($firstPart . "}");

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("The JSON response is malformed and it was not possible to decode!");
        }

        foreach ($decodedJson->stores as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle("Hartlauer " .$store->name)
                ->setStoreNumber(substr($store->ID, -3))
                ->setLatitude((string) $store->latitude)
                ->setLongitude((string) $store->longitude)
                ->setStreetAndStreetNumber($store->address1)
                ->setCity( $store->city)
                ->setZipcode($store->postalCode)
                ->setEmail($store->email)
                ->setPhoneNormalized($store->phone)
                ->setImage($store->custom->storeImage->webimage) // Around 500kb - Have other options such as 'mini'...
                ->setLogo($store->custom->storeImage->mini)
                ->setStoreHours($this->createStoreHoursString($store->storeHours)) // Mo 12:00 - 13:00, ...
            ;
            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }


    private function createStoreHoursString(array $storeHours) : string
    {
        $week = [];
        foreach ($storeHours as $hour) {
            $day = '';
            switch ($hour->day) {
                case 'week_monday':
                    $day = 'Mo ';
                    break;
                case 'week_tuesday':
                    $day = 'Di ';
                    break;
                case 'week_wednesday':
                    $day = 'Mi ';
                    break;
                case 'week_thursday':
                    $day = 'Do ';
                    break;
                case 'week_friday':
                    $day = 'Fr ';
                    break;
                case 'week_saturday':
                    $day = 'Sa ';
                    break;
            }

            $week[] = $day . $hour->openingTime;
        }

        if(array_key_exists(6, $week)) {
            array_pop($week);
        }

        return implode(', ', $week);
    }
}