<?php

/*
 * Store Crawler für Madonna Fashion (ID: 29095)
 */

class Crawler_Company_Madonna_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.madonna.eu/';
        $searchUrl = $baseUrl . '?site=stores';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="store_list"[^>]*>(.+?)</div>\s*</div>\s*</div#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list: ' . $searchUrl);
        }

        $pattern = '#<li[^>]*data-id="(\d{1,2})"[^>]*>(.+?)</ul>\s*</li#s';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list: ' . $searchUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeMatches[0]); $i++) {
            $pattern = '#class="street_number"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $storeMatches[2][$i], $streetNoMatch)) {
                $this->_logger->err($companyId . ': unable to get store street: ' . $storeMatches[1][$i]);
                continue;
            }

            $pattern = '#class="plz_country"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $storeMatches[2][$i], $cityMatch)) {
                $this->_logger->err($companyId . ': unable to get store city: ' . $storeMatches[1][$i]);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#class="phone"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $storeMatches[2][$i], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#class="fax"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $storeMatches[2][$i], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#class="opening_times"[^>]*>(.+)#';
            if (preg_match($pattern, $storeMatches[2][$i], $storeHoursListMatch)) {
                $pattern = '#class="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches)) {
                    $strTimes = '';
                    for ($j = 0; $j < count($storeHoursMatches[0]); $j++) {
                        $aDays = preg_split('#_#', $storeHoursMatches[1][$j]);
                        foreach ($aDays as $singleDay) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= $singleDay . ' ' . $storeHoursMatches[2][$j];
                        }
                    }
                }
            }

            $eStore->setStoreNumber($storeMatches[1][$i])
                    ->setAddress($streetNoMatch[1], $cityMatch[1])
                    ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
