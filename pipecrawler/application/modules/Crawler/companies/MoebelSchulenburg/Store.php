<?php

/**
 * Store Crawler für Möbel Schulenburg (ID: 80201)
 */
class Crawler_Company_MoebelSchulenburg_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.moebel-schulenburg.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $pStores = $sPage->getPage()->getResponseBody();

        # Step 1 - get detail-URL for all stores from page
        $pattern = "#<a href=\"/standorte/([^/]+?)\"#";
        if (!preg_match_all($pattern, $pStores, $storeUrls)) {
            throw new Exception($companyId . ' - no Store-URLS found at ' . $searchUrl);
        }
        $storeUrls = array_unique($storeUrls[1]);

        # Step 2 - parse detail page for each store with xpath to get the information
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach($storeUrls as $singleStoreurl) {
            $this->_logger->info("fetching Store-URL " . $searchUrl . $singleStoreurl . "/");
            $pStoreDetail = $sPage->getResponseAsDOM($searchUrl . $singleStoreurl. "/");
            $xpath = new DOMXPath($pStoreDetail);
            # class "map-wrapper" holds all the data we need

            $aInfos = [];
            $store =  $xpath->query('//div[@class="wb-locations--address"]')->item(0);#->textContent;
            # of there are geo coordinates, we add them
            $tel = $xpath->query('//div[@class="wb-locations--address"]//p[@class="wb-locations--address--phone-number"]')->item(0)->nodeValue;
            $email = $xpath->query('//div[@class="wb-locations--address"]//p[@class="wb-locations--address--email"]')->item(0)->nodeValue;
            $streetAddress = $xpath->query('//div[@class="wb-locations--address"]//p[@class="wb-locations--address--street"]')->item(0)->nodeValue;
            $zipAndCity = $xpath->query('//div[@class="wb-locations--address"]//p[@class="wb-locations--address-zip-city"]')->item(0)->nodeValue;
            $storeHours = $xpath->query('//div[@class="wb-locations--address"]//p[@class="shd-store-business-hours"]')->item(0)->nodeValue;

            $aInfos['url'] = $searchUrl . $singleStoreurl . '/';
            $aInfos['phone'] = trim($tel);
            $aInfos['email'] = trim($email);
            $aInfos['streetAddress'] = trim($streetAddress);

            preg_match_all("#(\d{5}\s+)([\D]+)#",$zipAndCity,$matches);
            $aInfos['postalCode'] = $matches[1][0];
            $aInfos['addressLocality'] = $matches[2][0];

            $storeHours = str_replace('Uhr','Uhr,',trim($storeHours));
            $aInfos['storeHours'] = substr($storeHours, 0, strlen($storeHours)-1);

            # add element
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber(trim($aInfos['streetAddress']))
                ->setZipcode(trim($aInfos['postalCode']))
                ->setCity(trim($aInfos['addressLocality']))
                ->setWebsite(trim($aInfos['url']))
                ->setStoreHoursNormalized(trim($aInfos['storeHours']))
                ->setLatitude(trim($aInfos['latitude']))
                ->setLongitude(trim($aInfos['longitude']))
                ->setPhoneNormalized(trim($aInfos['phone']))
                ->setFaxNormalized(trim($aInfos['fax']))
                ->setEmail(trim($aInfos['email']));
            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores, $companyId);
    }
}