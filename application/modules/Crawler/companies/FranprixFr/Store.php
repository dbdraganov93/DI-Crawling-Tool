<?php
/**
 * Store Crawler für Franprix FR (ID: 72369)
 */

class Crawler_Company_FranprixFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.franprix.fr/';
        $searchUrl = $baseUrl . 'locator/ajax/renderData';
        $sPage = new Marktjagd_Service_Input_Page();

        $aParams = array(
            'right_top_lat' => '51.0878',
            'right_top_lng' => '8.2301',
            'left_bottom_lat' => '42.3357',
            'left_bottom_lng' => '-5.1389',
            'lat' => '48.864716',
            'lng' => '2.349014000000011'
        );

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $sPage->open($searchUrl, $aParams);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $oPage = $sPage->getPage();
        $oPage->setMethod('GET');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->map->stores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->id)
                ->setStreetAndStreetNumber($singleJStore->address, 'fr')
                ->setCity($singleJStore->city)
                ->setZipcode(str_pad($singleJStore->postal_code, 5, '0', STR_PAD_LEFT))
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setWebsite($baseUrl . preg_replace('#^\/#', '', $singleJStore->url));

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#horaires\s*<\/h3>\s*(.+?)\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fr');
                }
            }

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}