<?php

/*
 * Store Crawler für JalouCity (ID: 29033)
 */

class Crawler_Company_JalouCity_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.jaloucity.de/';
        $searchUrl = $baseUrl . 'filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="(https://www.jaloucity.de/filialen/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches))
        {
            throw new Exception($companyId . ': unable to get store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl)
        {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#itemprop="(streetAddress|postalCode|addressLocality)"[^>]*>([^<]+?)<#';
            if (!preg_match_all($pattern, $page, $storeAddressMatches) || count($storeAddressMatches[1]) != 3)
            {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            
            $aAddress = array_combine($storeAddressMatches[1], $storeAddressMatches[2]);
            $aStreet = preg_split('#\s*,\s*#', $aAddress['streetAddress']);
            
            $pattern = '#itemprop="openingHours"[^>]*datetime="(.+?)(Bei[^<]+?)?<#';
            if (preg_match($pattern, $page, $storeHoursMatch))
            {
                $eStore->setStoreHoursNormalized(preg_replace('#([a-z])([A-Z])#', '$1,$2', $storeHoursMatch[1]))
                        ->setStoreHoursNotes($storeHoursMatch[2]);
            }
            
            $pattern = '#itemprop="(telephone|faxNumber|email)"[^>]*>(\s*<[^>]*>\s*)([^<]+?)<#';
            if (preg_match_all($pattern, $page, $storeContactMatches))
            {
                $aContact = array_combine($storeContactMatches[1], $storeContactMatches[3]);
            }
            
            $pattern = '#img[^>]*src="([^"]+?filiale[^"]+?jpg)"#';
            if (preg_match($pattern, $page, $storeImageMatch))
            {
                $eStore->setImage($storeImageMatch[1]);
            }
            
            $eStore->setAddress($aStreet[0], $aAddress['postalCode'] . ' ' . $aAddress['addressLocality'])
                    ->setPhoneNormalized($aContact['telephone'])
                    ->setFaxNormalized($aContact['faxNumber'])
                    ->setEmail($aContact['email']);
            
            if (array_key_exists(1, $aStreet))
            {
                $eStore->setSubtitle($aStreet[1]);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
