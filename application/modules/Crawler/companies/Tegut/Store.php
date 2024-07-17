<?php

/**
 * Store Crawler für tegut (ID: 349)
 */
class Crawler_Company_Tegut_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tegut.com';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $storesFound = [];
        $aAssignment = [];
        for ($i = 0; $i <= 15; $i++) {
            $htmlPage = $baseUrl . '/maerkte/marktsuche/seite/' . $i . '.html';
            $this->_logger->info($companyId . ': opening ' . $htmlPage);
            $sPage->open($htmlPage);
            $page = $sPage->getPage()->getResponseBody();

            $doc = $this->createDomDocument($page);
            $xpath = new DOMXPath($doc);

            $nodes = $xpath->query('//div[@class="search-details d-flex"]');

            foreach ($nodes as $node) {
                /** @var $node DOMElement */
                $title = $xpath->query($node->getNodePath() . '//h3[@class="h4 store-title float-left mr-1"]')
                    ->item(0);
                $readyTitle = trim($title->textContent);

                $url = $xpath->query($title->getNodePath() . '//a')->item(0)
                    ->getAttribute('href');
                $readyUrl = trim($url);

                $streetCityZip = $xpath->query($node->getNodePath() . '//div[@class="store_address txt-schwarz"]')
                    ->item(0);
                $splitAddress = explode(',', trim($streetCityZip->textContent));
                $streetAndNumber = $splitAddress[0];

                $splitCityZip = explode(' ', $splitAddress[1]);
                $zip = $splitCityZip[1];
                $city = $splitCityZip[2];

                $rawOpeningHoursTags = $xpath->query($node->getNodePath() . '//span[@class="span_markt_open_tag"]');
                $rawOpeningHoursTime = $xpath->query($node->getNodePath() . '//span[@class="span_markt_open_zeit"]');
                $openingHours = $this->normalizeOpenHours($rawOpeningHoursTags, $rawOpeningHoursTime);

                $storesFound[] = [
                    'title' => $readyTitle,
                    'city' => $city,
                    'zip' => $zip,
                    'streetAndNumber' => $streetAndNumber,
                    'url' => $readyUrl,
                    'hours' => $openingHours,
                ];
            }

            $pattern = '#var\s*markers_(\d+)\s*=\s*new\s*L\.Marker\(\[([^,]+?)\s*,\s*([^\]]+?)\]#';
            if (preg_match_all($pattern, $page, $geoMatches)) {
                for ($j = 0; $j < count($geoMatches[0]); $j++) {
                    $aGeoData[$geoMatches[1][$j]] = [
                        'latitude' => $geoMatches[2][$j],
                        'longitude' => $geoMatches[3][$j]
                    ];
                }
            }
            $pattern = '#markers_(\d+)\.bindPopup\([^)]*<a[^>]*href=\\\"(\\\/maerkte[^"]+?)"#';
            if (preg_match_all($pattern, $page, $assignmentMatches)) {
                for ($k = 0; $k < count($assignmentMatches[0]); $k++) {
                    $aAssignment[preg_replace(['#\\\\#', '#"#'], '', $assignmentMatches[2][$k])]['id'] = $assignmentMatches[1][$k];
                }
            }
            foreach ($aAssignment as $key => $aInfos) {
                $aAssignment[$key]['geo'] = $aGeoData[$aInfos['id']];
            }
        }

        if (empty($storesFound)) {
            throw new Exception('The crawler could not find any stores');
        }

        foreach ($storesFound as $storeData) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($storeData['title'])
                ->setStoreNumber($aAssignment[$storeData['url']]['id'])
                ->setCity($storeData['title'])
                ->setZipcode($storeData['zip'])
                ->setStreetAndStreetNumber($storeData['streetAndNumber'])
                ->setWebsite($baseUrl . $storeData['url'])
                ->setStoreHoursNormalized($storeData['hours'])
                ->setLatitude($aAssignment[$storeData['url']]['geo']['latitude'])
                ->setLongitude($aAssignment[$storeData['url']]['geo']['longitude']);

            if (preg_match('#Frankfurt\s*,\s*Flughafen#', $eStore->getCity())) {
                $eStore->setZipcode(60547);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

    private function createDomDocument(string $url): DOMDocument
    {
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        return $domDoc;
    }

    private function normalizeOpenHours($rawOpeningHoursTags, $rawOpeningHoursTime): string
    {
        $openingHours = [];
        foreach ($rawOpeningHoursTags as $key => $tags) {
            $openingHours[$key] = $tags->textContent;
        }

        foreach ($rawOpeningHoursTime as $key => $time) {
            $openingHours[$key] .= ' ' . $time->textContent;
        }

        return implode(',', $openingHours);
    }
}
