<?php

class Crawler_Company_Triumph_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        $generateLinks = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://storelocator.triumph.com/storelocator-solr/api/v1/'
                . 'stores?q=features%3A%28brand_triumph%29&pt='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '%2C'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&d=50&rows=1000';

        $aStoreLinks = $generateLinks->generateUrl($baseUrl);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $mjTimes = new Marktjagd_Service_Text_Times();

        foreach ($aStoreLinks as $sStoreLink) {

            try {
                $sPage->open($sStoreLink);
            } catch (Zend_Http_Client_Exception $e) {
                $logger->log($companyId . ': no stores at link \"' . $sStoreLink . '\" available.', Zend_Log::INFO);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#(\[\{.+\}\])\}\}#';
            if (!preg_match($pattern, $page, $match)) {
                continue;
            }
            $jStores = json_decode($match[1]);

            foreach ($jStores as $jStore) {
                if ($jStore->country != 'DE' || $jStore->priority != '1') {
                    continue;
                }

                $aCoords = preg_split('#\s*\,\s*#', $jStore->location);

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($jStore->id)
                        ->setTitle(ucwords(strtolower($jStore->name)))
                        ->setCity(ucwords(strtolower($jStore->city)))
                        ->setZipcode($jStore->zip)
                        ->setStreet($mjAddress->extractAddressPart('street', ucwords(strtolower($jStore->address))))
                        ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', ucwords(strtolower($jStore->address))))
                        ->setPhone($mjAddress->normalizePhoneNumber($jStore->phone))
                        ->setSubtitle($jStore->address2)
                        ->setFax($jStore->fax)
                        ->setWebsite($jStore->web)
                        ->setEmail($jStore->email)
                        ->setStoreHours($mjTimes->generateMjOpenings(preg_replace(array('#\/n#', '#(D0)#s'), array(', ', 'Do'), $jStore->opening_hours)))
                        ->setLongitude($aCoords[1])
                        ->setLatitude($aCoords[0]);

                if (strlen($eStore->getZipcode()) < 5) {
                    $eStore->setZipcode('0' . $eStore->getZipcode());
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
