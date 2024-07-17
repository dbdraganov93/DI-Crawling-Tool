<?php

/**
 * Storecrawler für ISOTEC (ID: 71388)
 */
class Crawler_Company_Isotec_Store extends Crawler_Generic_Company {

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'http://www.isotec.de/';
        $searchUrl = $baseUrl . 'fachbetriebssuche.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $aDetailUrls = array();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a\s*href="(fachbetriebe/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeDetailUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        foreach ($storeDetailUrlMatches[1] as $singleStoreDetailUrl) {
            if (!in_array($baseUrl . $singleStoreDetailUrl, $aDetailUrls)) {
                $aDetailUrls[] = $baseUrl . $singleStoreDetailUrl;
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aDetailUrls as $singleDetailUrl) {
            $sPage->open($singleDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<address[^>]*>(.+?)</address#';
            if (!preg_match($pattern, $page, $contactMatch)) {
                $this->_logger->err($companyId . ': unable to get contact infos: ' . $singleDetailUrl);
                continue;
            }
            $aAddress = preg_split('#\s*<[^>]*>\s*#', $contactMatch[1]);

            $pattern = '#<span[^>]*class="email"[^>]*>\s*<a[^>]*>\s*(.+?)\s*</a#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($sAddress->normalizeEmail($mailMatch[1]));
            }

            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#base[^>]*>\s*<title[^>]*>\s*(.+?)\s*<#';
            if (preg_match($pattern, $page, $titleMatch)
                    && !preg_match('#home#i', $titleMatch[1])) {
                $eStore->setTitle(preg_replace('#\s+\-\s+Isotec#i', '', $titleMatch[1]));
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 2])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 1]))
                    ->setWebsite($singleDetailUrl)
                    ->setStoreNumber($eStore->getHash());
            
            if (preg_match('#\[none\]#', $eStore->getStreet())
                    || preg_match('#spain#i', $eStore->getCity())
                    || strlen($eStore->getZipcode()) != 5) {
                continue;
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
