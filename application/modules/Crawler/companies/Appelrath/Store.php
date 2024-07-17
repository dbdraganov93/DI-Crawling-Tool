<?php

/*
 * Store Crawler für AppelrathCüpper (ID: 369)
 */

class Crawler_Company_Appelrath_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'https://www.appelrath.com/filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href=\"(https\:\/\/www\.appelrath.com\/filialen\/[^\/]+?\/)[^>]+?>\s*zur\s*filialseite#i';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        foreach ($storeUrlMatches[1] as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#class=\"address[^>]+?>([^<]+?)<[^>]+?>([^<]+?)<.+?<br\s*\/>([^<]+?)<[^>]+?>([^<]+?)<#i';
            if (!preg_match_all($pattern, $page, $addressMatches)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreet($sAddress->normalizeStreet($addressMatches[1][0]))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($addressMatches[2][0]))
                    ->setZipcode($addressMatches[3][0])
                    ->setCity($sAddress->normalizeCity($addressMatches[4][0]));
            
            $pattern ='#class=\"contact[^>]+?>[^>]+?>([^<]+?)<#';
            if (preg_match($pattern, $page, $telMatch)) {
                $eStore->setPhone(($sAddress->normalizePhoneNumber($telMatch[1])));
            } else {
                $this->_logger->warn('Company-ID ' . $companyId . ': unable to get store phone number on url ' . $singleUrl);
            }
            
            $pattern ='#class=\"contact.+?mailto\:([^\"]+?)\"#';
            if (preg_match($pattern, $page, $emailMatch)) {
                $eStore->setEmail($emailMatch[1]);
            } else {
                $this->_logger->warn('Company-ID ' . $companyId . ': unable to get store email on url ' . $singleUrl);
            }
            
            $pattern ='#Öffnungszeiten[^<]*<[^<]+?<[^>]+?>(.+?)<\/div>#';
            if (preg_match($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#<.+?>#', '', $storeHoursMatches[1])));
            } else {
                $this->_logger->warn('Company-ID ' . $companyId . ': unable to get store hours on url ' . $singleUrl);
            }
            
            $services = ',';
            $pattern ='#cms--teaserrow__headline[^>]+?>([^<]+?)<#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                foreach ($serviceMatches[1] as $singleService) {
                    $services .= ', ' . trim($singleService);
                }
                $eStore->setService(preg_replace('#,,\s#', '', $services));
            } else {
                $this->_logger->warn('Company-ID ' . $companyId . ': unable to get store services on url ' . $singleUrl);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
