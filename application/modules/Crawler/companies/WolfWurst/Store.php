<?php

/*
 * Store Crawler für Wolf Wurstwaren (ID: 71526)
 */

class Crawler_Company_WolfWurst_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://wolf-wurst.de/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&'
                . 'range=800&search_lat=51.16568789999999&'
                . 'search_lng=10.421041599999967&terms=71,72,73,74';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $aSections = array();
        foreach ($jStores as $singleJStore) {
            $aSections[
                    substr(
                            md5(
                                    $singleJStore->street
                                    . $singleJStore->city
                                    . $singleJStore->zipcode)
                            , 0, 15
                    )
                    ][] = ucwords(strtolower(preg_replace(array('#\s+[A-ZÄÖÜ][a-zäöü].+#', '#Ä#'), array('', 'ä'), $singleJStore->post_title)));
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleJStore->street)
                    ->setZipcode($singleJStore->zipcode)
                    ->setCity($singleJStore->city)
                    ->setStoreHoursNormalized($singleJStore->openings)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setWebsite($singleJStore->permalink)
                    ->setSection(implode(', ', $aSections[substr(md5(
                                                    $singleJStore->street
                                                    . $singleJStore->city
                                                    . $singleJStore->zipcode), 0, 15)]));
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
