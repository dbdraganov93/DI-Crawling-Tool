<?php

/**
 * Store Crawler für Equiva (ID: 69701)
 */
class Crawler_Company_Equiva_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $sJsonUrl = 'https://www.equiva.com/stores/locator/generateMapJSON/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($sJsonUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        if (!count($jStores->data) > 0) {
            throw new Exception('Company ID- ' .  $companyId . ': Unable to get json response for store list.');
        } else {
            $this->_logger->info('Company ID- ' .  $companyId . ': ' . count($jStores->data) . ' stores found.');
        }

        foreach ($jStores->data as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setZipcode($singleStore->zip)
                    ->setLatitude($singleStore->lat)
                    ->setLongitude($singleStore->lng)
                    ->setStoreNumber($singleStore->id)
                    ->setWebsite($singleStore->shoplink)
                    ->setCity($sAddress->normalizeCity($singleStore->city))
                    ->setEmail($sAddress->normalizeEmail($singleStore->email))
                    ->setPhone($sAddress->normalizePhoneNumber($singleStore->fon_prefix . $singleStore->fon))
                    ->setFax($sAddress->normalizePhoneNumber($singleStore->fax_prefix . $singleStore->fax))
                    ->setStreet($sAddress->normalizeStreet($singleStore->street))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($singleStore->street_no));

            $sPage->open($singleStore->shoplink);
            $page = $sPage->getPage()->getResponseBody();
            $pattern = '#<div\s*class=\"openings\">\s*<ul>(.+?)<\/ul>#';
            if (!preg_match($pattern, $page, $aStoreHourMatch)) {
                $this->_logger->warn('Compandy ID- ' . $companyId . ': unable to get opening hours from url ' . $singleStore->shoplink);
            } else {
                $eStore->setStoreHours($sTimes->generateMjOpenings($aStoreHourMatch[1]));
            }

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
