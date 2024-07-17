<?php
/* 
 * Store Crawler für Sky Deutschland (ID: 72049)
 */
class Crawler_Company_SkyDeutschland_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $sUrl = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://business.sky.de/sbs/skyfinder.servlet?group=H&group=B&group=A&group=3D&simpleSearch=Suchen'
            . '&searchTerm=' . $sUrl::$_PLACEHOLDER_ZIP . '&orderBy=NAME_ASCENDING&country=de&action=search';

        $aUrls = $sUrl->generateUrl($baseUrl, 'zip');
        
        $sPage = new Marktjagd_Service_Input_Page(true);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $url) {
            $sPage->open($url);
            $response = $sPage->getPage()->getResponseBody();

            $response = str_replace("\\u001A", "", $response);

            $sText = new Marktjagd_Service_Text_Encoding();
            $response = $sText::toUTF8($response);

            if ($url == 'http://business.sky.de/sbs/skyfinder.servlet?group=H&group=B&group=A&group=3D&simpleSearch=Suchen'
                . '&searchTerm=49751&orderBy=NAME_ASCENDING&country=de&action=search') {
            }
            $json = json_decode($response);

            foreach ($json->currentData as $jStore) {
                //Hotels nicht mit aufnehmen
                if ($jStore->mapdata->type =='H') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($jStore->id);
                $eStore->setTitle($jStore->name);
                $eStore->setLatitude($jStore->mapdata->latitude);
                $eStore->setLongitude($jStore->mapdata->longitude);
                $eStore->setStreetAndStreetNumber($jStore->description->street);
                $eStore->setZipcodeAndCity($jStore->description->city);

                if ($jStore->description->program) {
                    $eStore->setText($jStore->description->program);
                }

                $cStores->addElement($eStore);
            }
        }

        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
