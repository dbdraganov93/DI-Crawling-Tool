<?php

/**
 * Store Crawler für Kräuter Kühne (ID: 67850)
 */
class Crawler_Company_KraeuterKuehne_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.kraeuter-kuehne.de/';
        $searchUrl = $baseUrl . 'kraeuter-kuehne-filialen';
        $sPage = new Marktjagd_Service_Input_Page();
            
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h3[^>]*>([^<]+)</h3>\s*(<div[^>]*>\s*)?<table[^<]*>(.+?)</table>#';
        if (!preg_match_all($pattern, $page, $cityMatches)) {
            throw new Exception('unable to get stores grouped by city: '  . $searchUrl);
        }

        $strTimes = '';
        if (preg_match('#<strong>Öffnungszeiten</strong>(.+?)<br[^>]*>\s*<br[^>]*>#', $page, $storeHoursMatch)){
            $strTimes = $storeHoursMatch[1];
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($cityMatches[3] as $singleCity) {
            $pattern = '#<tr[^>]*>(.+?)</tr>#';
            if (!preg_match_all($pattern, $singleCity, $storeMatches)) {
                throw new Exception('unable to get any stores: '  . $searchUrl);
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>([^<]+?)(\s*<[^>]*>\s*[^<]+?)?<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    throw new Exception($companyId . ': unable to get store adddress from ' . $singleStore);
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#<img[^>]*src="([^"]+?Filiale[^"]+?)"#';
                if (preg_match($pattern, $singleStore, $imageMatch)) {
                    $eStore->setImage($imageMatch[1]);
                }
                
                $eStore->setAddress($addressMatch[1], $addressMatch[3])
                        ->setStoreHoursNormalized($strTimes);
                
                $cStores->addElement($eStore);

            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
