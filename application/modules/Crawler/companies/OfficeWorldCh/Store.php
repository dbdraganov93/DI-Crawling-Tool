<?php

/* 
 * Store Crawler für Office World CH (ID: 72226)
 */

class Crawler_Company_OfficeWorldCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.officeworld.ch/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*ow-filialbox-row"[^>]*>(.+?)<div[^>]*filialliste-trenner#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeMatches[1]) as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+?)<\/table#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#pickupplus#i';
            if (preg_match($pattern, $singleStore, $sectionMatch)) {
                $eStore->setSection('PickupPlus Abholstelle');
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'CH');

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}