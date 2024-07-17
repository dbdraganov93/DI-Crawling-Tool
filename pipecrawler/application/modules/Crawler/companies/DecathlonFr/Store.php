<?php
/**
 * Store Crawler für Decathlon FR (ID: 72370)
 */

class Crawler_Company_DecathlonFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.decathlon.fr';
        $searchUrl = $baseUrl . '/store-locator';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*data-store-name="#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $aDays = array(
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        );
        
        $cStores = new Marktjagd_Collection_Api_Store();     

        $storeElements = $sPage->getDomElsFromUrlByClass($searchUrl, 'st-bl-mag', 'div', true);
        
        foreach ($storeElements as $storeElement) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $singleStoreUrl = $storeElement->getElementsByTagName('a')[0]->getAttribute('href');
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            $phoneRaw = $sPage->getDomElsFromDomEl($storeElement, 'number')[0]->textContent;
            $eStore->setPhoneNormalized($phoneRaw);            
            $storeTitle = $storeElement->getElementsByTagName('a')[0]->textContent;
            $eStore->setTitle($storeTitle);
            $eStore->setWebsite($storeDetailUrl);

            $storeUrlParts = explode('-', $singleStoreUrl);
            $storeIdElement = count($storeUrlParts) - 1;
            $storeId = $storeUrlParts[$storeIdElement];            
            $storeDetailsJSON = $this->getStoreHours($storeId);
            $storeDetailsArray = json_decode($storeDetailsJSON);                

            if ($storeDetailsArray) {
                if (isset($storeDetailsArray->features[0]->properties->opening_hours)) {
                    $strOpeningTimes = '';
                    $openingHours = $storeDetailsArray->features[0]->properties->opening_hours->usual;

                    foreach ($openingHours as $key => $times) {
                        if (empty($times)) {
                            continue;
                        }

                        if (strlen($strOpeningTimes)) {
                            $strOpeningTimes .= ', ';
                        }

                        else {
                            $dayTiming = $times[0]->start. ' - ' .$times[0]->end;
                        }
                        
                        $strOpeningTimes .= $aDays[$key] .'  '. $dayTiming; 
                    }
                    $eStore->setStoreHours($strOpeningTimes);   
                }

                if (isset($storeDetailsArray->features[0]->geometry->coordinates)) {
                    $eStore->setLatitude($storeDetailsArray->features[0]->geometry->coordinates[1]);
                    $eStore->setLongitude($storeDetailsArray->features[0]->geometry->coordinates[0]);
                }

                $eStore->setCity($storeDetailsArray->features[0]->properties->address->city);

                if (isset($storeDetailsArray->features[0]->properties->address->zipcode)) {
                    $eStore->setZipcode($storeDetailsArray->features[0]->properties->address->zipcode);
                }

                if (isset($storeDetailsArray->features[0]->properties->address->lines)) {
                    $addressLines = $storeDetailsArray->features[0]->properties->address->lines;

                    if (is_array($addressLines) && !empty($addressLines)) {
                        $eStore->setStreetAndStreetNumber($addressLines[0], 'FR');
                        $streetAndNumber = $eStore->getStreet();

                        foreach ($addressLines as $key => $line) {
                            
                            if ($key == 0) {
                                continue;
                            }                            
                            if (!empty($line)) {
                                $streetAndNumber .= " ".$line;
                            }
                        }
                        $eStore->setStreet($streetAndNumber);
                    }
                }
            }
            $cStores->addElement($eStore); 
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * Function to get store opening hours 
     * 
     * @param string $storeId 
     * @param string $errorCode
     *
     * @return string
     */
    private function getStoreHours($storeId, $errorCode = '200')
    {
        $apiUrl = 'https://api.woosmap.com/stores/search/?key=woos-c7283e70-7b4b-3c7d-bbfe-e65958b8769b&query=idstore%3A%22'. $storeId .'%22';

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = array();
        $headers[] = 'Origin: https://www.decathlon.de';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        if (!preg_match('#(' . $errorCode . ')#', $info['http_code'])) {
            return NULL;
        }
        return $response;
    }
}