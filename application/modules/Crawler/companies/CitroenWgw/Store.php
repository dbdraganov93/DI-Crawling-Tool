<?php
/**
 * Store Crawler für Citroen WGW (ID: )
 */

class Crawler_Company_CitroenWgw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.citroen.at/';
        $searchUrl = $baseUrl . '_/Layout_Citroen_PointsDeVente/getStoreList?area=1000'
            . '&lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&long=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores as $singleJStore) {
                $aAddress = preg_split('#\s*<[^>]*>\s*#', $singleJStore->address);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->rrdi)
                    ->setAddress($aAddress[0], $aAddress[1])
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}