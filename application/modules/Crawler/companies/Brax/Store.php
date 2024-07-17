<?php

/**
 * Storecrawler für BRAX (ID: 70967)
 */
class Crawler_Company_Brax_Store extends Crawler_Generic_Company
{

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'https://corporate.brax.com';
        $storeListUrl = $baseUrl . '/api/storefinder?sr-storefinder=&language=de_DE'
            . '&webspaceKey=lp_brax_com&s[]=braxstore'
            . '&lat=' . $sGenerator::$_PLACEHOLDER_LAT
            . '&lng=' . $sGenerator::$_PLACEHOLDER_LON;

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $aUrls = $sGenerator->generateUrl($storeListUrl, 'coords', 1);

        $aStoreIds = array();

        foreach ($aUrls as $storeUrl) {
            $sPage->open($storeUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores as $singleJStore)
            {
                if (array_key_exists($singleJStore->id, $aStoreIds)
                    || strlen($singleJStore->zip) != 5
                ) {
                    continue;
                }

                $aStoreIds[$singleJStore->id] = $singleJStore->id;
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->street)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->street)))
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setEmail($sAddress->normalizeEmail($singleJStore->email))
                    ->setWebsite($singleJStore->website)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                    ->setImage($baseUrl . $singleJStore->imageUrl)
                    ->setStoreNumber($singleJStore->id);

                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();
                $pattern = '#ffnungszeiten\s*</div>\s*(.+?)\s*</div>#is';

                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                }

                $cStore->addElement($eStore);
            }
        }



        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
