<?php

/**
 * Store Crawler für McFit (ID: 29103)
 */
class Crawler_Company_McFit_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mcfit.com';
        $searchUrl = $baseUrl . '/typo3conf/ext/bra_studioprofiles_mcfitcom/Resources/'
                . 'Public/Json/studios_de.json?origAddress=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Service_Generator_Url();

        $aUrls = $sDbGeo->generateUrl($searchUrl, 'zipcode', 100);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            
            if (!count($jStores)) {
                continue;
            }
            
            foreach ($jStores as $singleJStore) {
                if (!preg_match('#^\+49#', $singleJStore->phone) || !preg_match('#\d{5}#', $singleJStore->postal)) {
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->studioCode)
                        ->setLatitude($singleJStore->lat)
                        ->setLongitude($singleJStore->lng)
                        ->setStreetAndStreetNumber($singleJStore->address)
                        ->setZipcode($singleJStore->postal)
                        ->setCity($singleJStore->city)
                        ->setPhoneNormalized($singleJStore->phone)
                        ->setSection(implode(', ', $singleJStore->filterCybertraining))
                        ->setService(implode(', ', $singleJStore->filterSpecials))
                        ->setWebsite($baseUrl . $singleJStore->detailPageLink);

                if ($singleJStore->badges->parking) {
                    $eStore->setParking('vorhanden');
                }

                if (preg_match('#24h\s+geöffnet#', $singleJStore->badges->businessHours)) {
                    $eStore->setStoreHoursNormalized('Mo-So 00:00-24:00');
                } else {
                    $eStore->setStoreHoursNormalized($singleJStore->badges->businessHours);
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
