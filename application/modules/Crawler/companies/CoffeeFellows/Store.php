<?php

/**
 * Storecrawler für Krass Optik (ID: 71026)
 */
class Crawler_Company_CoffeeFellows_Store extends Crawler_Generic_Company {

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'https://www.coffee-fellows.com/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&lat=50&lng=10&max_results=1000&radius=1000';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#Deutschland#', $singleJStore->country)) {
                continue;
            }

            $aImages = array();
            foreach ($singleJStore as $singleFieldKey => $singleFieldValue) {
                if (preg_match('#my_img_url#', $singleFieldKey) && strlen($singleFieldValue)) {
                    if (!preg_match('#^http#', $singleFieldValue)) {
                        $aImages[] = $baseUrl . preg_replace('#\/wp-content#', 'wp-content', $singleFieldValue);
                    } else {
                        $aImages[] = $singleFieldValue;
                    }
                }
                if (count($aImages) == 3) {
                    break;
                }
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleJStore->address)
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setStoreNumber($singleJStore->id)
                    ->setWebsite($singleJStore->permalink)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setFaxNormalized($singleJStore->fax)
                    ->setEmail($singleJStore->email)
                    ->setStoreHoursNormalized($singleJStore->hours, 'text', true)
                    ->setImage(implode(',', $aImages));

            $cStores->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
