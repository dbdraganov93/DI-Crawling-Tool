<?php

/*
 * Store Crawler für Knutzen (ID: 70950)
 */

class Crawler_Company_Knutzen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.knutzen.de/';
        $searchUrl = $baseUrl . 'StoreLocator/search';

        $aRadius = array(
            '24975' => 16,
            '25746' => 21,
            '25899' => 20,
            '25813' => 17,
            '24376' => 25,
            '21465' => 10,
            '22047' => 9,
            '21079' => 12,
            '22880' => 9,
            '25524' => 15,
            '25337' => 11,
            '25541' => 12,
            '23701' => 16,
            '23758' => 25,
            '24539' => 19,
            '24143' => 12,
            '24217' => 18,
            '23562' => 12,
            '24558' => 16,
            '24782' => 9,
            '24837' => 10,
            '24340' => 15
        );

        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="store(.+?)<\/button>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#^-(\d+)"#';
            if (preg_match($pattern, $singleStore, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax\s*[\.|\:]\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)<\/p>#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#<a[^>]*href="\/(einrichtungshaus[^"]+?)"#';
            if (preg_match($pattern, $singleStore, $urlMatch)) {
                $eStore->setWebsite($baseUrl . $urlMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2]);

            if (in_array($eStore->getZipcode(), $aRadius)) {
                $eStore->setDefaultRadius($aRadius[$eStore->getZipcode()]);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
