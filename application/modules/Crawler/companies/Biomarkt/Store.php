<?php

/**
 * Storecrawler für Biomarkt (ID: 29067)
 */
class Crawler_Company_Biomarkt_Store extends Crawler_Generic_Company
{
    /**
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $cStores         = new Marktjagd_Collection_Api_Store();
        $sPage           = new Marktjagd_Service_Input_Page();
        $baseUrl         = 'https://www.biomarkt.de/';
        $searchParameter = '%22size%22:1000';
        $searchUrl =  $baseUrl . 'api/es/market/_search/?source=%7B%22from%22:0,' . $searchParameter .
            ',%22sort%22:[],%22query%22:%7B%22bool%22:%7B%22must%22:[]%7D%7D%7D&source_content_type=application%2Fjson';

        $sPage->open($searchUrl);
        $jsonResponse = $sPage->getPage()->getResponseAsJson();

        if (!count($jsonResponse->hits->hits)) {
            throw new Exception($companyId . ': unable to get store list from ' . $baseUrl);
        }

        foreach ($jsonResponse->hits->hits as $store) {
            $eStore      = new Marktjagd_Entity_Api_Store();
            $storeFields = [];

            $storeFields['id'] = $this->limitIdCharacters($store->_id);

            foreach ($store->_source as  $key => $storeInfo) {
                switch ($key) {
                    case 'address.city':
                        $storeFields['city'] = $storeInfo;
                        break;
                    case 'address.lat':
                        $storeFields['lat'] = $this->isLatLonValid($storeInfo, 'lat');
                        break;
                    case 'address.lon':
                        $storeFields['lon'] = $this->isLatLonValid($storeInfo, 'lon');
                        break;
                    case 'address.street':
                        $storeFields['street'] = $storeInfo;
                        break;
                    case 'address.zip':
                        $storeFields['zip'] = $storeInfo;
                        break;
                    case 'contact.phone':
                        $storeFields['phone'] = $storeInfo;
                        break;
                    case 'name':
                        $storeFields['name'] = $storeInfo;
                        break;
                    case 'openingHoursMarket':
                        $storeFields['storeHours'] = $storeInfo;
                        break;
                }
            }

            // In case there is only Lat or Lon
            if (array_key_exists('lat', $storeFields) &&
                array_key_exists('lon', $storeFields) &&
                ($storeFields['lat'] == '' || $storeFields['lon'] == '')
            ) {
                $storeFields['lat'] = '';
                $storeFields['lon'] = '';
            }

            $this->replaceJsonProblems($storeFields);

            $eStore->setStoreNumber($storeFields['id'])
                ->setCity($storeFields['city'])
                ->setLatitude(array_key_exists('lat', $storeFields) ? $storeFields['lat'] : '')
                ->setLongitude(array_key_exists('lon', $storeFields) ? $storeFields['lon'] : '')
                ->setStreetAndStreetNumber($storeFields['street'])
                ->setZipcode($storeFields['zip'])
                ->setPhoneNormalized(array_key_exists('phone', $storeFields) ? $storeFields['phone'] : '')
                ->setTitle($storeFields['name'])
                ->setStoreHoursNormalized($this->generateStoreHours($storeFields['storeHours']))
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function generateStoreHours(array $storeHours) : string
    {
        $result = [];
        foreach ($storeHours as $daysOfTheWeek) {
            if (isset($daysOfTheWeek->open_from) && isset($daysOfTheWeek->open_until)) {
                $result[] = substr($daysOfTheWeek->weekday, 0, 2) . ' ' . $daysOfTheWeek->open_from .
                    ' - ' . $daysOfTheWeek->open_until;
            }
        }

        return implode(', ', $result);
    }

    private function isLatLonValid(string $value, string $type) : string
    {
        $result = '';

        // Filter degrees strings
        if(!preg_match('#E|N#', $value) ) {
            return $value;
        }

        if($type == 'lat') {
            $lat = (int) substr($value, 0, 2);
            if($lat > 35 && $lat < 71){
                $result = $value;
            }
        } else {
            $lon = (int) substr($value, 0, 2);
            if($lon > 35 && $lon < 71){
                $result = $value;
            }
        }

        return $result;
    }

    private function limitIdCharacters(string $id)
    {
        if(strlen($id) > 32) {
            return substr($id, 0, 30);
        }

        return $id;
    }

    private function replaceJsonProblems(&$storeFields)
    {
        // Exception for markt 475360 Wolfsburg that have wrong Lat Lon
        if($storeFields['id'] == '475360') {
            unset($storeFields['lat']);
            unset($storeFields['lon']);
        }

        // Replacement for Grosskarolinenfeld 82109
        if($storeFields['zip'] == '82109') {
            $storeFields['zip'] = '83109';
        }

        // Replacement for Wolfsburg 38448
        if($storeFields['street'] == 'Hoffmannstr. 10') {
            $storeFields['zip'] = '38442';
        }

        // Replacement for Lüneburg-Häcklingen 21335
        if($storeFields['city'] == 'Lüneburg-Häcklingen') {
            $storeFields['city'] = 'Lüneburg';
            $storeFields['street'] = str_replace('1', '2', $storeFields['street']);
        }

        // Replacement for Frankfurt 604890
        if($storeFields['zip'] == '604890') {
            $storeFields['zip'] = substr($storeFields['zip'], 0, -1);
        }

        // Replacement for Rilchingen-Hanweiler 66270
        if($storeFields['zip'] == '66270') {
            $storeFields['zip'] = '66271';
        }
    }
}
