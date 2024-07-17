<?php

/*
 * Store Crawler für Ehrhardt (ID: 70838)
 */

class Crawler_Company_EhrhardtReifen_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.reifen-ehrhardt.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $aCampaign = array(
            'goettingen@reifen-ehrhardt.de',
            'braunschweig@reifen-ehrhardt.de',
            'salzgitter@reifen-ehrhardt.de',
            'langenhagen@reifen-ehrhardt.de',
            'anderten@reifen-ehrhardt.de',
            'neustadt@reifen-ehrhardt.de',
            'walsrode@reifen-ehrhardt.de',
            'lehrte@reifen-ehrhardt.de',
            'halberstadt@reifen-ehrhardt.de',
            'nordhausen@reifen-ehrhardt.de'
        );

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Standorte(.+?)Gewerbekunden#s';
        if (!preg_match($pattern, $page, $storeUrlListMatch)) {
            throw new Exception($companyId . ': unable to get store url list.');
        }

        $pattern = '#<a[^>]*href=\'(http:\/\/www\.reifen-ehrhardt\.de\/standorte\/[^\']+?)/\' data-level=\'2\'>#s';
        if (!preg_match_all($pattern, $storeUrlListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#fon:\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax:\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#<p[^>]*>\s*([^<\@]+?\@[^<]+?)\s*<#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
                if (in_array($eStore->getEmail(), $aCampaign)) {
                    $eStore->setDistribution('April Kampagne');
                }
            }

            $pattern = '#ffnungszeiten(.+?)</div>\s*</div>\s*</div#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3])
                    ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
