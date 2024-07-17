<?php

/**
 * Store Crawler für Karstadt (ID: 98)
 */
class Crawler_Company_Karstadt_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.karstadt.de/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-Karstadt-Site/de/Stores-Find';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select[^>]*name=".+storelocator_store[^>]*>(.+?)</select>#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<option[^>]*value="([0-9]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeNumberMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeNumberMatches[1] as $singleStoreNumber) {
            $storeUrl = $baseUrl . 'on/demandware.store/Sites-Karstadt-Site/de/Stores-Details?StoreID=' . $singleStoreNumber;
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            if ($companyId == '67152' && !preg_match('#data-storename=".+?Sporthaus.+?"#si', $page)) {
                continue;
            }
            if ($companyId != '67152' && preg_match('#data-storename=".+?Sporthaus.+?"#si', $page)) {
                continue;
            }
           
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#address[^>]*box.+?Öffnungszeiten(.+?)</p#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $pattern = '#<div[^>]*class="mk"[^>]*>(.+?)</div#s';
            $strSection = '';
            $strNotes = '';
            if (preg_match($pattern, $page, $serviceMatch)) {
                $pattern = '#<p[^>]*>(.+?)</p>#s';
                if (preg_match_all($pattern, $serviceMatch[1], $serviceDetailMatches)) {
                    foreach ($serviceDetailMatches[1] as $singleService) {                        
                        if (strlen($strNotes)) {
                            $strNotes .= ',';
                        }
                        $strNotes .= strip_tags(preg_replace('#\s*<br[^>]*>\s*#', ' ', $singleService));                        
                    }
                }
            }

            $pattern = '#<div[^>]*class="viewport".+?<img[^>]*src="([^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($imageMatch[1]);
            }

            $eStore->setStoreNumber($singleStoreNumber)
                    ->setWebsite($storeUrl)                
                    ->setSection($strSection)
                    ->setStoreHoursNotes($strNotes);
            
            if (preg_match_all('#<span[^>]*itemprop="([^"]+)"[^>]*>(.+?)</span>#', $page, $matches)){
                foreach ($matches[1] as $idx => $val){
                    switch ($val) {
                        case 'streetAddress': $eStore->setStreetAndStreetNumber(strip_tags($matches[2][$idx]));
                            break;                        
                        case 'postalCode': $eStore->setZipcode($matches[2][$idx]);
                            break;                        
                        case 'addressLocality': $eStore->setCity($matches[2][$idx]);
                            break;
                        case 'telephone': $eStore->setPhoneNormalized($matches[2][$idx]);
                            break;
                        case 'faxNumber': $eStore->setFaxNormalized($matches[2][$idx]);
                            break;
                    }
                }
            }

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
