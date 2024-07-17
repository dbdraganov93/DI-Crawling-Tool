<?php

/**
 * Store Crawler für BoConcept (ID: 71665)
 */
class Crawler_Company_BoConcept_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.boconcept.com';
        $searchUrl = $baseUrl . '/de-de/stores/find-your-local-store';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*storelocatoroptions\s*\=\s*(.+?);#is';
        if (!preg_match($pattern, $page, $storeMatch)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        $jStores = json_decode($storeMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setLatitude($singleJStore->Latitude)
                    ->setLongitude($singleJStore->Longitude)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->Address2)))
                ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->Address2)))
                ->setZipcode($sAddress->extractAddressPart('zipcode', $singleJStore->Address4))
                ->setCity($sAddress->extractAddressPart('city', $singleJStore->Address4))
                ->setEmail($singleJStore->EmailLink->Text)
                    ->setWebsite($baseUrl . $singleJStore->DirectionLink);
            
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#ffnungszeiten(.+?)</table#is';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#<img\s*class="footer-payment"[^>]*title="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $paymentMatches)) {
                $eStore->setPayment(implode(', ', $paymentMatches[1]));
            }
            
            $pattern = '#' . $eStore->getEmail() . '.+?Tel:\s*([^<]+?)\s*<#s';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#<img[^>]*title="[^"]+?top1"\s*src="([^"]+?\.jpg)"#i';
            if (preg_match($pattern, $page, $imageMatch)) {
                    $eStore->setImage($baseUrl . $imageMatch[1]);
                }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
