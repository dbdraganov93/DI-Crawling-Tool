<?php

/*
 * Store Crawler für Bäckerei Dreißig (ID: 71524)
 */

class Crawler_Company_BaeckereiDreissig_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.baeckerei-dreissig.de/';
        $searchUrl = $baseUrl . 'StoreLocator/search?lat=50&lng=10&distance=1000';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="itemWrap"[^>]*data-id="(\d{4})"[^>]*>(.+?)</script>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeMatches[0]); $i++) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[^<]+?)\s*<[^>]*>\s*deutschland#i';
            if (!preg_match($pattern, $storeMatches[2][$i], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $pattern = '#ffnungszeiten(.+?)</div>\s*<br#';
            if (!preg_match($pattern, $storeMatches[2][$i], $storeHoursMatch)) {
                $this->_logger->info($companyId . ': unable to get store hours.');
            }

            $pattern = '#tel([^<]+?)<#i';
            if (!preg_match($pattern, $storeMatches[2][$i], $phoneMatch)) {
                $this->_logger->info($companyId . ': unable to get store phone number.');
            }
            
            $pattern = '#<strong[^>]*>\s*([^<]+?)\s*</strong>\s*<br[^>]*>\s*([^<]+?Uhr[^<]*?)\s*<#';
            if (!preg_match_all($pattern, $storeMatches[2][$i], $storeHoursNotesMatches)) {
                $this->_logger->info($companyId . ': unable to get store hours notes.');
            }
            
            $strStoreHoursNotes = '';
            for ($j = 0; $j < count ($storeHoursNotesMatches[0]); $j++) {
                if (strlen($strStoreHoursNotes)) {
                    $strStoreHoursNotes .= ', ';
                }
                $strStoreHoursNotes .= $storeHoursNotesMatches[1][$j] . ' ' . $storeHoursNotesMatches[2][$j];
            }

            $eStore = new Marktjagd_Entity_Api_Store();
                        
            $eStore->setStoreNumber($storeMatches[1][$i])
                    ->setAddress($addressMatch[1], $addressMatch[2])
                    ->setStoreHoursNormalized($storeHoursMatch[1])
                    ->setPhoneNormalized($phoneMatch[1])
                    ->setStoreHoursNotes($strStoreHoursNotes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
