<?php

/* 
 * Store Crawler für Selgros (ID: 72232)
 */

class Crawler_Company_Selgros_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl   = 'https://www.selgros.de';
        $searchUrl = $baseUrl . '/marktsuche';
        $marketUrl = $baseUrl . '/markt/';
        $cStores   = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Match markets URLs and emails
        $marketUrlPattern = '#(?="email":"(?<emails>[^;]+?)",")|(?="detail_url":"\\\/markt\\\/(?<cities>[^"]*)")#';
        if(!preg_match_all($marketUrlPattern, $page, $pageMatches)) {
            throw new Exception(
                $companyId . ' -> Was not possible to get any Store Url and Emails from: ' . $searchUrl
            );
        }

        foreach($pageMatches['cities'] as $key => $city) {
            if($city == '') {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $curl = curl_init($marketUrl . $city);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($curl);
            curl_close($curl);

            $addressPattern = '#address-line1">(?<address>[^"]*)<\/#';
            if(!preg_match($addressPattern, $response, $addressMatch)) {
                throw new Exception(
                    $companyId . ' -> Was not possible to match any address line ' . $marketUrl . $city
                );
            }

            $zipPattern = '#postal-code">(?<zip>[^"]*)<\/#';
            if(!preg_match($zipPattern, $response, $zipMatch)) {
                $this->_logger->warn('Was not possible to match the zip code in ' . $marketUrl . $city);
            }

            $faxPhonePattern = '#phone-link">(?<phone>[^"]*)<\/a|fax">(?<fax>[^"]*)<\/div#';
            if(!preg_match_all($faxPhonePattern, $response, $faxPhoneMatch)) {
                $this->_logger->warn(
                    'Was not possible to match the Phone and Fax code in ' . $marketUrl . $city
                );
            }

            $openingHoursPattern = '#field--day_of_week">(?<content>[^"]*)<\/#';
            if(!preg_match_all($openingHoursPattern, $response, $openingHoursMatch)) {
                $this->_logger->warn(
                    'Was not possible to match the OpeningHours code in ' . $marketUrl . $city
                );
            }

            $eStore->setTitle($city)
                ->setCity($city)
                ->setWebsite($marketUrl . $city)
                ->setEmail($pageMatches['emails'][$key -1]) // Have to subtract one to match with cities key
                ->setStreetAndStreetNumber($addressMatch['address'])
                ->setZipcode($zipMatch['zip'])
                ->setPhoneNormalized($faxPhoneMatch['phone'][0])
                ->setFaxNormalized($faxPhoneMatch['fax'][1])
                ->setStoreHoursNormalized($this->generateStoreHoursString($openingHoursMatch['content']))
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function generateStoreHoursString(array $storesHoursRaw): string
    {
        $result = [];
        foreach ($storesHoursRaw as $storeHoursRaw) {
            $result[] = trim(strip_tags($storeHoursRaw));
        }

        return implode(', ', $result);
    }
}
