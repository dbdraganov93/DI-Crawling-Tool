<?php

/**
 * Store Crawler für Bauspezi (ID: 29030)
 */
class Crawler_Company_Bauspezi_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $searchUrl = 'http://s455617704.online.de/bauspezi/standortsuche/process.php'
            . '?p=displayStores&criteria=%7B%22address%22:%2268960%22,%22lat%22:%2247.5653819%22,%22'
            . 'lng%22:%227.312207100000023%22,%22page_number%22:1,%22nb_display%22:1000,%22'
            . 'category_id%22:%22%22,%22max_distance%22:%221000%22,%22display_type%22:%222%22%7D';

        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $aStores = $sPage->getPage()->getResponseAsJson();

        foreach ($aStores->locations as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle('bauSpezi Baumarkt');
            $eStore->setSubtitle($jStore->name);
            $eStore->setStoreNumber($jStore->id);
            $eStore->setLatitude($jStore->lat);
            $eStore->setLongitude($jStore->lng);
            $eStore->setWebsite($jStore->url);
            $eStore->setEmail($jStore->email);
            $aAddress = explode(', ', $jStore->address);

            $eStore->setStreetAndStreetNumber($aAddress[0]);
            $eStore->setZipcodeAndCity($aAddress[1]);
            $eStore->setPhoneNormalized($jStore->tel);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}