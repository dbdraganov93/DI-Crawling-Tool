<?php

/*
 * Store Crawler für Möbel Knappstein (ID: 71737)
 */

class Crawler_Company_MoebelKnappstein_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.moebel-knappstein.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a\s*href="\/(standorte\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Adresse:\s*</strong>\s*<br[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*,\s*#', $addressMatch[1]);

            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Tel[^<]*?</strong>\s*<br[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax[^<]*?</strong>\s*<br[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)\s*</div>\s*</div>\s*</div#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#E-Mail-[^<]*?</strong>\s*<br[^>]*>\s*<a[^>]*href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($aAddress[count($aAddress) - 3])
                    ->setZipcode(($aAddress[count($aAddress) - 2]))
                    ->setCity($aAddress[count($aAddress) - 1]);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
