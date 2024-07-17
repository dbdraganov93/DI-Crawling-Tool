<?php

/**
 * Store Crawler für Touratech (ID: 71418)
 */
class Crawler_Company_Touratech_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.touratech.de/';
        $searchUrl = $baseUrl . 'ueber-touratech/touratech-deutschland.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="vcard"[^>]*>(.+?)</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            if (preg_match('#Headquarter#', $singleStore)) {
                continue;
            }

            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*class="url"#';
            if (preg_match($pattern, $singleStore, $urlMatch)) {
                $eStore->setWebsite($urlMatch[1]);
            }

            $pattern = '#class="fn"[^>]*>\s*(.+?)\s*<#';
            if (preg_match($pattern, $singleStore, $titleMatch)) {
                $eStore->setTitle($titleMatch[1]);
            }

            $pattern = '#class="tel\s*phone"[^>]*>(.+?)<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#class="email"[^>]*>(.+?)<#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#class="street-address"[^>]*>(.+?)(</div|<br[^>]*>\+)#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);

            $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 1]))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 2])));

            if (preg_match('#Germany#', $aAddress[2])) {
                $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])));
            }
            
            $eStore->setStoreNumber($eStore->getHash());
            
            if (count($aAddress) == 3 && !preg_match('#Germany#', $aAddress[2])) {
                $eStore->setSubtitle($aAddress[0]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
