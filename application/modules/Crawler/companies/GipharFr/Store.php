<?php
/**
 * Store Crawler für Pharmacien Giphar FR (ID: )
 */

class Crawler_Company_GipharFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://pharmacies.pharmaciengiphar.com/';
        $searchUrl = $baseUrl . 'controller/liste-magasins/json/controller/giphar/region/0/departement/0/code_insee_ville/0';
        $sPage = new Marktjagd_Service_Input_Page();

        $aParams = array(
            'perpage' => '10000',
            'distance' => '50',
            'maxitems' => '10000'
        );

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_REFERER, 'http://pharmacies.pharmaciengiphar.com/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Origin: http://pharmacies.pharmaciengiphar.com',
            'X-Requested-With: XMLHttpRequest'
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        $pattern = '#infoMagasins\s*=\s*(.+?);#s';
        if (!preg_match($pattern, $result, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setWebsite($singleJStore->url)
                ->setStreetAndStreetNumber($singleJStore->adresse1, 'fr')
                ->setZipcode($singleJStore->codePostal)
                ->setCity(ucwords(strtolower($singleJStore->ville)))
                ->setPhoneNormalized($singleJStore->telephone);

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#itemprop="openingHours"[^>]*content="([^"]+?)"#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
            }
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}