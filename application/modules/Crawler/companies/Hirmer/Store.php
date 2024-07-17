<?php

/*
 * Store Crawler für Hirmer (ID: 69158)
 */

class Crawler_Company_Hirmer_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.hirmer-grosse-groessen.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#filiallist[^>]*>\s*<li[^>]*>\s*(.+?)</ul#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#filial_address[^>]*>.*?<p[^>]*>\s*(.+?)\s*</p#';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $aAddress = preg_split('#(\s*<[^>]*>\s*)+#', $storeAddressMatch[1]);

            $pattern = '#filial_info_contact[^>]*>\s*(.+?)\s*<br[^>]*>\s*<br[^>]*>#';
            if (preg_match($pattern, $page, $storeContactMatch)) {
                $aContact = preg_split('#(\s*<[^>]*>\s*)+#', $storeContactMatch[1]);
                $eStore->setPhoneNormalized($aContact[0])
                        ->setFaxNormalized($aContact[1]);
            }

            $pattern = '#ffnungszeiten(.+?)<a#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $storeMailMatch)) {
                $eStore->setEmail($storeMailMatch[1]);
            }

            $eStore->setAddress($aAddress[0], $aAddress[1])
                    ->setWebsite($singleStoreUrl);

            if (strlen($eStore->getZipcode()) != 5) {
                continue;
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
