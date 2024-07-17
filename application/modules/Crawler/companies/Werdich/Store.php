<?php

/*
 * Store Crawler für Werdich (ID: 29146)
 */

class Crawler_Company_Werdich_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.werdich.com/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<option[^>]*value="Filiale[^"]*wählen"[^>]*>(.+?)</select#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<option[^>]*value="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            if (!preg_match('#^http#', $singleStoreUrl)) {
                continue;
            }
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="filiale_adresse"[^>]*>\s*<[^>]*>\s*([^<]+?<[^>]*>)?\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<[^>]*>#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#Tel([^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)<img#is';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setAddress(preg_replace('#([a-zäöü])([A-ZÄÖÜ])#', '$1 $2', $addressMatch[2]), $addressMatch[3])
                    ->setSubtitle(strip_tags($addressMatch[1]));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
