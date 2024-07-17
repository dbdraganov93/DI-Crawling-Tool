<?php

/**
 * Storecrawler für vivesco Apotheken (ID: 22384)
 */
class Crawler_Company_Vivesco_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $baseUrl = 'http://www.vivesco.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $searchUrl = $baseUrl . 'standard-store-locator-service-portlet/api/'
                . 'secure/jsonws/storesjson/get-stores?companyId=2656198';
        $aDayPattern = array(
            '#monday#',
            '#tuesday#',
            '#wednesday#',
            '#thursday#',
            '#friday#',
            '#saturday#',
            '#sunday#',
        );

        $aDayReplacement = array(
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa',
            'So',
        );

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store-list-page.');
        }

        $page = $sPage->getPage()->getResponseBody();
        $jStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $mjTimes = new Marktjagd_Service_Text_Times();

        foreach ($jStores->stores as $jSingleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            if (strlen($jSingleStore->external_website)) {
                $eStore->setWebsite($jSingleStore->external_website);
            }
            if ($jSingleStore->organization_id == '4528155'
                || $jSingleStore->organization_id == '3200209'
                || $jSingleStore->organization_id == '5772442'
            ) {
                continue;
            }
            $sTimes = '';
            foreach ($jSingleStore->opening_hours->administrative as $singleDay) {
                if ($singleDay->open != '-1' && $singleDay->close != '-1') {
                    if (strlen($sTimes)) {
                        $sTimes .= ', ';
                    }
                    if (strlen((string)$singleDay->open) == 3) {
                        $sOpen = preg_replace('#([0-9]{1})([0-9]{2})#', '$1:$2', $singleDay->open);
                    }
                    if (strlen((string)$singleDay->open) == 4) {
                        $sOpen = preg_replace('#([0-9]{2})([0-9]{2})#', '$1:$2', $singleDay->open);
                    }
                    if (strlen((string)$singleDay->close) == 3) {
                        $sClose = preg_replace('#([0-9]{1})([0-9]{2})#', '$1:$2', $singleDay->close);
                    }
                    if (strlen((string)$singleDay->close) == 4) {
                        $sClose = preg_replace('#([0-9]{2})([0-9]{2})#', '$1:$2', $singleDay->close);
                    }
                    $sTimes .= $singleDay->day . ' ' . $sOpen . '-' . $sClose;
                }
            }

            $sTimes = preg_replace($aDayPattern, $aDayReplacement, $sTimes);

            $eStore ->setStoreHours($mjTimes->generateMjOpenings($sTimes))
                    ->setStreet($mjAddress->extractAddressPart('street', $jSingleStore->address->street1))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $jSingleStore->address->street1))
                    ->setCity($jSingleStore->address->city)
                    ->setZipcode(str_pad($jSingleStore->address->postal_code, '5', '0', STR_PAD_LEFT))
                    ->setStoreNumber($jSingleStore->organization_id)
                    ->setPhone($mjAddress->normalizePhoneNumber($jSingleStore->telephone))
                    ->setEmail($jSingleStore->email->{'email-address'}[0]->address)
                    ->setLongitude($jSingleStore->custom->Longitude->value)
                    ->setLatitude($jSingleStore->custom->Latitude->value)
                    ->setLogo($jSingleStore->custom->Logo_Apotheke->value);

                    if (!strlen($eStore->getEmail())) {
                        $eStore->setEmail('info@vivesco.de');
                    }
            $cStores->addElement($eStore,TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
