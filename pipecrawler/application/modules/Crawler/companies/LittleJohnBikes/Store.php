<?php


/**
 * Store Crawler für Little John Bikes (ID: 29209)
 */
class Crawler_Company_LittleJohnBikes_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.littlejohnbikes.de';
        $searchUrl = $baseUrl . '/shops.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $patternStoreList = '#<div[^>]*id="shop\_([0-9]{1,4})"[^>]*data-lat="(.*?)"[^>]*data-long="(.*?)"[^>]*>(.*?)</div>#s';
        if (!preg_match_all($patternStoreList, $page, $matchStoreList)) {
            throw new Exception('no stores found on ' . $searchUrl);
        }

        foreach ($matchStoreList[4] as $keyStore => $storeInfos) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($matchStoreList[1][$keyStore]);
            $eStore->setLatitude($matchStoreList[2][$keyStore]);
            $eStore->setLongitude($matchStoreList[3][$keyStore]);

            $patternUrl = '#<a[^>]*href="(.*?)"[^>]*>.*?zum\s*Shop</a>#s';
            if (preg_match($patternUrl, $storeInfos, $matchUrl)) {
                $eStore->setWebsite($baseUrl . $matchUrl[1]);
            }

            $patternAddress = '#<p[^>]*>(.*?)</p>#s';
            if (preg_match_all($patternAddress, $storeInfos, $matchAddress)) {
                // Adressdaten
                $address = $matchAddress[1][0];
                $aAddress = explode('<br />', $address);

                $eStore->setStreetAndStreetNumber($aAddress[0]);
                $eStore->setZipcodeAndCity($aAddress[1]);

                // Telefon, Fax, Mail
                $contactInfos = $matchAddress[1][1];
                $aContactInfos = explode('<br />', $contactInfos);
                foreach ($aContactInfos as $sContactInfos) {
                    if (preg_match('#Telefon\:\s*(.*?)$#s', $sContactInfos, $matchTelefon)) {
                        $eStore->setPhoneNormalized($matchTelefon[1]);
                    }

                    if (preg_match('#Telefax\:\s*(.*?)$#s', $sContactInfos, $matchTelefax)) {
                        $eStore->setFaxNormalized($matchTelefax[1]);
                    }

                    if (preg_match('#Email\:\s*(.*?)$#s', $sContactInfos, $matchMail)) {
                        $eStore->setEmail($matchMail[1]);
                    }
                }

                // Öffnungszeiten
                $contactInfos = $matchAddress[1][3];
                $sOpenings = '';

                $patternDay = '\s*(Mo|Di|Mi|Do|Fr|Sa|So)[\.|\:]{0,2}\s*[\-|\–]*\s*(Mo|Di|Mi|Do|Fr|Sa|So)*[\.|\:]*\s*';
                $patternTimes = '\s*([0-9]{1,2})\s*[\-|\–]\s*([0-9]{1,2})\s*';
                $pattern = '#' . $patternDay . $patternTimes . '#s';

                if (preg_match_all($pattern, $contactInfos, $matchesOpenings)) {
                    foreach ($matchesOpenings[0] as $keyOpenings => $opening) {
                        if ($keyOpenings > 0) {
                            $sOpenings .= ', ';
                        }

                        $sOpenings .= $matchesOpenings[1][$keyOpenings];

                        if (trim($matchesOpenings[2][$keyOpenings]) != '') {
                            $sOpenings .= '-' . $matchesOpenings[2][$keyOpenings];
                        }

                        $sOpenings .= ' ' . $matchesOpenings[3][$keyOpenings] . ':00';
                        $sOpenings .= '-' . $matchesOpenings[4][$keyOpenings] . ':00';
                    }
                    $eStore->setStoreHoursNormalized($sOpenings);
                }

            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}