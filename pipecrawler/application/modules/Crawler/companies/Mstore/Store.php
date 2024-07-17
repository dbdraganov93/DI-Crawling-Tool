<?php

/**
 * Storecrawler für mStore (ID: 69976)
 */
class Crawler_Company_Mstore_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $baseUrl = 'http://www.mstore.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Filialen\s*</a>\s*<ul[^>]*>(.+?)</ul#';
        if (!preg_match($pattern, $page, $aStoreListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="' . $baseUrl . '((mm-trading|mstore).+?)"#';
        if (!preg_match_all($pattern, $aStoreListMatch[1], $aStoreUrlMatches)) {
            throw new Exception($companyId . ': unable to find any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();

        foreach ($aStoreUrlMatches[1] as $sSingleStoreUrl) {
            if (!$sPage->open($baseUrl . $sSingleStoreUrl)) {
                $logger->log($companyId . ': unable to open store detail page for url '
                        . $baseUrl . $sSingleStoreUrl, Zend_Log::ERR);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#Kontakt\s*(</strong>\s*<br[^>]*>)?(.+?)</p#';
            if (!preg_match($pattern, $page, $aAddressMatch)) {
                $logger->log($companyId . ': unable to get address for url '
                        . $baseUrl . $sSingleStoreUrl, Zend_Log::ERR);
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $aAddressMatch[count($aAddressMatch) - 1]);

            if (1 < count($aAddress)) {
                $eStore->setSubtitle(trim($aAddress[0]));
            }

            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]));

            $aSplittedAddress = preg_split('#\s*\,\s*#', $aAddress[count($aAddress) - 1]);

            if (2 == count($aSplittedAddress)) {
                $eStore->setStreet($sAddress->extractAddressPart('street', $aSplittedAddress[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aSplittedAddress[0]));
            }

            $eStore->setCity($sAddress->extractAddressPart('city', $aSplittedAddress[count($aSplittedAddress) - 1]))
                    ->setZipcode($sAddress->extractAddressPart('zip', $aSplittedAddress[count($aSplittedAddress) - 1]));

            $pattern = '#<div[^>]*id="center"[^>]*>\s*<h1[^>]*>\s*(.+?)\s*</h1#';
            if (preg_match($pattern, $page, $aTitleMatch)) {
                $eStore->setTitle($aTitleMatch[1]);
            }

            $pattern = '#href="tel:(.+?)"#';
            if (preg_match($pattern, $page, $aTelMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($aTelMatch[1]));
            }

            $pattern = '#Telefax:(.+?)<#';
            if (preg_match($pattern, $page, $aFaxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($aFaxMatch[1]));
            }

            $pattern = '#href="mailto:(.+?)"#';
            if (preg_match($pattern, $page, $aMailMatch)) {
                $eStore->setEmail($aMailMatch[1]);
            }

            $pattern = '#Öffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $page, $aStoreHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($aStoreHoursMatch[1]));
            }

            $pattern = '#<img[^>]*src="([^"]*\/uploads\/mstores\/[^"]*)"#';
            if (preg_match($pattern, $page, $aImageMatch)) {
                $eStore->setImage($aImageMatch[1]);
                if (!preg_match('#^(http)#', $eStore->getImage())) {
                    $eStore->setImage('http://www.mstore.de' . $eStore->getImage());
                }
            }

            $eStore->setWebsite($baseUrl . $sSingleStoreUrl)
                    ->setStoreNumber($sSingleStoreUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}