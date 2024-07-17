<?php

/**
 * Store Crawler für Rossmann (ID: 26)
 */
class Crawler_Company_Rossmann_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://service.123map.de/';
        $latLngUrl = 'https://geocoder.123map.de/geocoder_json.pl?thm=rossmann-prod1&limit=1000&zipcode=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($latLngUrl, 'zipcode', 10);

        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($aUrls as $singleUrl) {
            try {
                $sPage->open($singleUrl);
                $jCoords = $sPage->getPage()->getResponseAsJson();

                foreach ($jCoords as $singleCoords) {
                    if (is_object($singleCoords)) {
                        $searchUrl = $baseUrl . 'maps/map.js?thm=rossmann-prod1&psx=659&psy=590'
                                . '&acy=' . $singleCoords->lat
                                . '&acx=' . $singleCoords->lng . '&ar=10000&jix=2&debug=0'
                                . '&slt_rossmann=id%20address_city%20address_street%20address_zip%20hours_value';


                        $sPage->open($searchUrl);
                        $page = $sPage->getPage()->getResponseBody();

                        $pattern = '#tr:\s*(\{.+?\}\})#';
                        if (!preg_match($pattern, $page, $storeJsonMatch)) {
                            continue;
                        }

                        $jStores = json_decode($storeJsonMatch[1])->bA;
                        if (is_null($jStores)) {
                            continue;
                        }

                        foreach ($jStores as $singleJStore) {
                            $eStore = new Marktjagd_Entity_Api_Store();

                            $eStore->setStoreNumber($singleJStore[0])
                                    ->setCity($singleJStore[1])
                                    ->setStreetAndStreetNumber($singleJStore[2])
                                    ->setZipcode($singleJStore[3])
                                    ->setStoreHoursNormalized(preg_replace(array('#\'#', '#\[#', '#\]#', '#\{#', '#\}#'), '', $singleJStore[4]));

                            $cStores->addElement($eStore);
                        }
                    }
                }
            }
            catch (Exception $e) {
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
