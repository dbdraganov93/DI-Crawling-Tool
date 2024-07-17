<?php

/**
 * Store Crawler für Pirelli (drivercenter.eu) (ID: 80264)
 */
class Crawler_Company_Pirelli_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.drivercenter.eu/';
        $searchUrl = $baseUrl . 'de-de/dealer-locator';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->getPage()->setTimeout(120);
        $sPage->open($searchUrl);
        $pStores = $sPage->getPage()->getResponseBody();

        # Step 1 - get detail-URL for all stores from page
        $pattern = "#<a href=\"https:\/\/www.drivercenter.eu\/de-de\/standorte\/([^\"]+?)\"#";
        if (!preg_match_all($pattern, $pStores, $storeUrls)) {
            throw new Exception($companyId . ' - no Store-URLS found at ' . $searchUrl);
        }
        $storeUrls = array_unique($storeUrls[1]);

        # Step 2 - parse detail page for each store with xpath to get the information
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach($storeUrls as $singleStoreurl) {
            $this->_logger->info("fetching Store-URL " . $baseUrl . 'de-de/standorte/' . $singleStoreurl);
            $pStoreDetail = $sPage->getResponseAsDOM($baseUrl . 'de-de/standorte/' . $singleStoreurl);
                        $xpath = new DOMXPath($pStoreDetail);

            # script-tag holds all the data we need
            $store =  json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent);

            #normalize store hours
            $openingHours = '';
            foreach($store->openingHoursSpecification as $dayOfWeek) {

                if(!empty($openingHours) && !empty($dayOfWeek->opens)) {
                    $openingHours .= ', ';
                }
                if(!empty($dayOfWeek->opens)) {
                    $openingHours .= $dayOfWeek->dayOfWeek[0] . " " . $dayOfWeek->opens . " - " . $dayOfWeek->closes;
                }
            }

           # add element
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($store->address->streetAddress)
                ->setZipcode($store->address->postalCode)
                ->setCity($store->address->addressLocality)
                ->setWebsite($baseUrl . 'de-de/standorte/' . $singleStoreurl)
                ->setStoreHoursNormalized($openingHours)
                ->setLatitude($store->geo->latitude)
                ->setLongitude($store->geo->longitude)
                ->setPhoneNormalized($store->telephone)
                ->setFaxNormalized($store->fax)
                #->setImage($store->image)
                ->setTitle($store->name);
            $cStores->addElement($eStore);

        }
        return $this->getResponse($cStores, $companyId);
    }
}