<?php

/**
 * Storecrawler für Superdry (ID: 69938)
 */
class Crawler_Company_Superdry_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $sGenerator = new Marktjagd_Service_Generator_Url();

        $searchUrl = 'http://www.superdry.de/index.php?option=com_store_collect'
                . '&lng=' . $sGenerator::$_PLACEHOLDER_LON
                . '&lat=' . $sGenerator::$_PLACEHOLDER_LAT
                . '&limit=1000&format=raw&task=nearest';
        $aUrl = $sGenerator->generateUrl($searchUrl, 'coords', 0.5);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $mjTimes = new Marktjagd_Service_Text_Times();

        foreach ($aUrl as $url) {
            if (!$sPage->open($url)) {
                throw new Exception('unable to open site for company-id ' . $companyId);
            }
            $page = $sPage->getPage()->getResponseBody();
            $aStores = json_decode($page);
            if (!count($aStores)) {
                $logger->log('no stores available for company with id ' . $companyId, Zend_Log::WARN);
            }

            foreach ($aStores as $aStore) {
                if ($aStore->country != 'Germany'
                        || strlen($aStore->postcode) != 5) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStreet($mjAddress->extractAddressPart('street', $aStore->address))
                        ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aStore->address))
                        ->setCity($aStore->city)
                        ->setZipcode($aStore->postcode)
                        ->setPhone($mjAddress->normalizePhoneNumber($aStore->phone))
                        ->setLatitude($aStore->longitude)
                        ->setLongitude($aStore->latitude);

                $sTimes = '';
                foreach ($aStore->openingHours as $aHours) {
                    $aHours->hours = preg_replace('#([0-9]{2})\s*\-\s*([0-9]{2})#', ':$1-$2:', $aHours->hours);
                    if (strlen($sTimes)) {
                        $sTimes .= ', ';
                    }
                    $sTimes .= $aHours->day . ' ' . $aHours->hours;
                }
                $eStore->setStoreHours($mjTimes->generateMjOpenings($sTimes));

                $cStores->addElement($eStore);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
