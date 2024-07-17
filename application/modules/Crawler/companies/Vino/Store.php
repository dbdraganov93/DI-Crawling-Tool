<?php

class Crawler_Company_Vino_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.vino24.de/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="plus_link\s*marketLink"[^>]*>\s*<a[^>]*href="(.+?)"[^>]*>#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStoreLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $searchUrl = $baseUrl . $singleStoreLink;
            if (!$sPage->open($searchUrl)) {
                $this->_logger->err($companyId . ': unable to open detail page for ' . $searchUrl);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<table[^>]*class="vino_market_sidebar"[^>]*>(.+?)</table>#';
            if (!preg_match_all($pattern, $page, $searchMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos.');
                continue;
            }

            $eStore->setStoreHours($sTimes->generateMjOpenings($searchMatches[1][0]));

            $pattern = '#<strong[^>]*>(.+?)<#';
            if (preg_match($pattern, $searchMatches[1][1], $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }

            $pattern = '#<table[^>]*class="vino_address_sidebar\s*vino_market_sidebar"[^>]*>(.+?)</table>#';
            if (!preg_match_all($pattern, $page, $addressMatches)) {
                $this->_logger->err($companyId . ': unable to get any store address infos.');
                continue;
            }

            $pattern = '#<strong[^>]*>(.+?)<br[^>]*>\s*<br[^>]*>#';
            if (!preg_match($pattern, $addressMatches[1][0], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address');
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatch[1]);

            $strSubtitle = '';
            $strZipCity = '';
            $strStreet = '';
            foreach ($aAddress as $singleAddress) {
                if (preg_match('#^([0-9]{5})#', $singleAddress)) {
                    $strZipCity = $singleAddress;
                    continue;
                }
                if (preg_match('#^[0-9]+#', $sAddress->extractAddressPart('streetnumber', strip_tags($singleAddress)))) {
                    $strStreet = strip_tags($singleAddress);
                    continue;
                }
                $strSubtitle = strip_tags($singleAddress);
            }

            $eStore->setStreet($sAddress->extractAddressPart('street', $strStreet))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $strStreet))
                    ->setZipcode($sAddress->extractAddressPart('zip', $strZipCity))
                    ->setCity($sAddress->extractAddressPart('city', $strZipCity))
                    ->setSubtitle($strSubtitle)
                    ->setStoreNumber($eStore->getHash());

            if ($eStore->getZipcode() == '23569') {
                $eStore->setStreet($aAddress[0])
                        ->setSubtitle($sAddress->extractAddressPart('street', $strStreet));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}