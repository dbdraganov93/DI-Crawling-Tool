<?php
/*
 * Store Crawler für Brillen Rottler (ID: 71012)
 */

class Crawler_Company_Rottler_Store extends Crawler_Generic_Company
{

    public function crawl($companyId) {

        $baseUrl = "https://www.rottler.de/";
        $searchUrl = $baseUrl . "standort-waehlen/";
        $sPage = new Marktjagd_Service_Input_Page();

        $storeArray = $this->getStoreUrls($searchUrl, $sPage, $companyId);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($storeArray as $store) {
            $storeInfo = $this->getStoreInfos($store, $sPage, $companyId);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setAddress($storeInfo["streetNumber"], $storeInfo["zipCity"])
                ->setStoreHoursNormalized($storeInfo["hours"])
                ->setPhone($storeInfo["telefon"])
                ->setEmail($storeInfo["email"])
                ->setWebsite($storeInfo["url"]);

            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores, $companyId);

    }

    private function getStoreUrls($url,$sPage, $companyId) {
        $storeArray = array();
        $stores = $sPage->getDomElFromUrlByID($url, 'shop-list');
        if (empty($stores)) {
            $this->_logger->err($companyId . ': unable to get any stores from ' . $url);
        }
        // starts with 1 to leave out overview site
        for ($i=1; $i<sizeof($stores->getElementsByTagName('a'))-1; $i++) {
            array_push($storeArray, $stores->getElementsByTagName('a')[$i]->getAttribute('href'));
        }
        return $storeArray;
    }

    private function getStoreInfos($url, $sPage, $companyId) {
        $address = $sPage->getDomElsFromUrlByClass($url, 'address-box');
        $cleanedString = preg_replace('#\s{2,}#', ' ', $address[0]->textContent);

//        $patternAddress = '#\b([A-zßäüö\.\-]+\s[Str\.]*\s{0,1}[\d\-\w]+)\s(\d{5}\s[a-zäüöß\-\s]*\b)\sTelefon#i';
        $patternAddress = '#\b(((\bam\b)|(\blange\b))?\s?[A-zßäüö\.\-]+\s[Str\.]*\s{0,1}[\d\-\w]+)\s(\d{5}\s[a-zäüöß\-\s]*\b)\sTelefon#i';
        $patternTel = '#Telefon:\s([\d\s]+)#';
        $patternEmail = '#Email:\s([a-z\(\)\-\.]*)\b#';

        if (!preg_match($patternAddress, $cleanedString, $address)) {
            $this->_logger->err($companyId . ': unable to get any store address from ' . $url);
        }
        if (!preg_match($patternTel, $cleanedString, $tel)) {
            $this->_logger->warn($companyId . ': unable to get phone number from ' . $url);
        }
        if (!preg_match($patternEmail, $cleanedString, $email)) {
            $this->_logger->warn($companyId . ': unable to get mail address from ' . $url);
        }
        $email[1] = preg_replace('#\(at\)#', '@', $email[1]);
        $hours = $this->storeHours($cleanedString);
        if (empty($hours)) {
            $this->_logger->warn($companyId . ': unable to get store hours from ' . $url);
        }

        return [
            "streetNumber" => $address[1],
            "zipCity" => $address[5],
            "hours" => $hours,
            "telefon" => $tel[1],
            "email" => $email[1],
            "url" => $url
        ];
    }

    private function storeHours($data) {
        $patternHours = '#zeiten\s[a-z\s\-]*:[\s\d\-\:uhrmidofr]*[\w]*:[\s\d\:\-]*#i';
        $str = html_entity_decode($data);
        $str =  preg_replace('/[^a-z|\s+|^\d|^:-]+/i', '',$str);
        $str = preg_replace('#\s{2,}#', ' ', $str);
        preg_match($patternHours, $str, $hours);
        // to catch break times
        $hours[0] = preg_replace('#(Uhr)\s(\d)#', "$1, $2", $hours[0]);
        // to catch all days
        $hours[0] = preg_replace('#(Uhr)(\w)#', "$1 $2", $hours[0]);
        return $hours[0];
    }
}
