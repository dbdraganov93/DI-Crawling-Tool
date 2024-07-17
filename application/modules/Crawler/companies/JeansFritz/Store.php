<?php

/*
 * Store Crawler für Jeans Fritz (ID: 28828)
 */

class Crawler_Company_JeansFritz_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.jeans-fritz.com/';
        $sPage = new Marktjagd_Service_Input_Page();

        $aStoreUrls = array();
        for ($counter = 0; $counter < 10; $counter++) {
            $searchUrl = $baseUrl . 'content/filialfinder.html?tx_mfjeansfritzstore_pi1[BEREICH]=' . $counter . '9999';
            
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a[^>]*href="([^"]+?HAENDLER[^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeUrlMatches))
            {
                $aStoreUrls = array_merge($aStoreUrls, $storeUrlMatches[1]);
            }
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $singleStoreUrl)
        {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#extendedstoreinfo"[^>]*>.+?<p[^>]*>\s*(.+?)\s*<br[^>]*>\s*<br[^>]*>#';
            if (!preg_match($pattern, $page, $storeAddressMatch))
            {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*<[^>]*>\s*#', $storeAddressMatch[1]);
            
            $pattern = '#fon:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $storePhoneMatch))
            {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursMatch))
            {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($aAddress[0], $aAddress[1]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
