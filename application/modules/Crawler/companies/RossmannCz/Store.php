<?php

/**
 * Store Crawler für Rossmann CZ (ID: 81487)
 */
class Crawler_Company_RossmannCz_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $baseUrl = 'https://www.rossmann.cz/';
        $searchUrl = $baseUrl . 'prodejny';

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $xpath = $this->createXPathFromUrl($page);

        $nodes = $xpath->query('/html/body/main/div[2]/div[3]/a');
        foreach ($nodes as $storeNodes) {
            $title = $xpath->query($storeNodes->getNodePath() . '//div[@class="page-store--store-title"]')
                ->item(0)->textContent;

            $rawAddress = $xpath->query($storeNodes->getNodePath() . '//div[@class="page-store--store-address"]')
                ->item(0)->textContent;
            if (!preg_match('#(?<zip>\d{1,})\s(?<city>([^,]*)),\s?(?<address>[^$]*)#', $rawAddress, $addressMatch)) {
                $this->_logger->warn('No store address could be matched: '.$rawAddress);
                continue;
            }

            $rawPhone = $xpath->query($storeNodes->getNodePath() . '//div[@class="page-store--store-phone"]')
                ->item(0)->textContent;
            if (!preg_match('#Tel.:\s?(?<phone>[^$]*)#', $rawPhone, $phoneMatch)) {
                $this->_logger->warn('No store phone could be matched: '.$rawPhone.' but no worries, the store is added');
            }

            $openingHoursNode = $xpath->query($storeNodes->getNodePath() . '//div[@class="page-store--opening-hours"]');
            $openingHours = $this->openingHours($openingHoursNode, $xpath);

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore
                ->setTitle('Rossmann: ' . $title)
                ->setStreetAndStreetNumber($addressMatch['address'])
                ->setZipcode($addressMatch['zip'])
                ->setCity($addressMatch['city'])
                ->setStoreHoursNormalized(implode(',', $openingHours))
                ->setPhoneNormalized($phoneMatch['phone'])
                ->setWebsite($baseUrl . $storeNodes->getAttribute('href'))
                ->setLatitude($storeNodes->getAttribute('data-latitude'))
                ->setLongitude($storeNodes->getAttribute('data-longitude'))
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function createXPathFromUrl(string $url): DOMXPath
    {
        // ignore warnings
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        return new DOMXPath($domDoc);
    }

    private function openingHours(DOMNodeList $openingHoursNode, DOMXPath $xpath): array
    {
        $openingHours = [];
        foreach ($openingHoursNode as $openingHour) {
            $openingHoursDailyNodes = $xpath->query($openingHour->getNodePath() . '//div[@class="page-store--opening-day"]');
            /** @var DOMElement $openingHourNode */
            foreach ($openingHoursDailyNodes as $openingHourNode) {
                $czOpeningHour = trim($openingHourNode->textContent);

                if (preg_match('#Zavřeno#', $czOpeningHour)) {
                    // skip day that does not open
                    continue;
                }

                $openingHours[] = preg_replace(
                    ['#Po#', '#Út#', '#St#', '#Čt#', '#Pá#', '#So#', '#Ne#'],
                    ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'],
                    $czOpeningHour
                );
            }
        }

        return $openingHours;
    }
}
