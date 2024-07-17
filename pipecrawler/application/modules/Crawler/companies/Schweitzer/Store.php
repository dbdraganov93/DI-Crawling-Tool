<?php

/**
 * Store Crawler für Schweitzer Fachinformationen (ID: 71408)
 */
class Crawler_Company_Schweitzer_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.schweitzer-online.de/';
        $searchUrl = $baseUrl . 'info/Standorte-Uebersicht-Karte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        Zend_Debug::dump($page);die;
        $pattern = '#href="' . $searchUrl . '(.+?)</ul#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#href="([^"]+?)\?#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*class="content_info_contribution_wrapper"[^>]*>.+?(Buchhandlung|Verwaltung|Vertriebsbüro).+?>([^>]+?)\s*<br[^>]*>(\(.+?\s*<br[^>]*>)?\s*([0-9]{5}.+?)<#is';
            if (!preg_match($pattern, $page, $addressDetailMatch)) {
                $this->_logger->err($companyId . ': unable to get store address details: ' . $singleUrl);
                continue;
            }
            
            $pattern = '#Telefon(.+?)(,|<)#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#Telefax(.+?)(,|<)#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#Öffnungszeiten(.+?)</span#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
                        
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressDetailMatch[2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressDetailMatch[2])))
                    ->setCity($sAddress->extractAddressPart('city', $addressDetailMatch[4]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressDetailMatch[4]))
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
