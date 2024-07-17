<?php

/* 
 * Store Crawler für Loesdau Pferdesport (ID: 71836)
 */

class Crawler_Company_Loesdau_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.loesdau.de/';
        $searchUrl = $baseUrl . 'Pferdesporthaeuser.htm?websale7=loesdau&tpl=s-f-filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*class="level2"[^>]*id="ihr-loesdau_menu_links_filialen"[^>]*>(.+?)<hr[^>]*class="level3"[^>]*id="ihr-loesdau_menu_trennstrich#is';
        if (!preg_match($pattern, $page, $storeUrlListMatch))
        {
            throw new Exception ($companyId . ': unable to get store url list.');
        }
        
        $pattern = '#<a[^>]*href=\'([^\']+?)\&Ctx#';
        if (!preg_match_all($pattern, $storeUrlListMatch[1], $storeUrlMatches))
        {
            throw new Exception ($companyId . ': unable to get any store urls from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl)
        {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#itemprop="(streetAddress|postalCode|addressLocality)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $storeAddressMatches))
            {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            $aAddress = array_combine($storeAddressMatches[1], $storeAddressMatches[2]);
            
            $pattern = '#ffnungszeiten\:?\s*<[^>]*>(.+?<)div#';
            if (!preg_match($pattern, $page, $storeHoursListMatch))
            {
                $this->_logger->err($companyId . ': unable to get store hours: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#(content=[\'|\"]([^\'\"]+?)[\'|\"]\s*)?itemprop=[\'|\"]openingHours[\'|\"](\s*content=[\'|\"]([^\'\"]+?)[\'|\"])?#';
            if (!preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches))
            {
                $this->_logger->err($companyId . ': unable to get any store hours from list: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#<strong[^>]*>([^<]+?)</strong>([^<]+?)\s*<#';
            if (preg_match($pattern, $storeHoursListMatch[1], $storeHoursNotesMatch))
            {
                $eStore->setStoreHoursNotes($storeHoursNotesMatch[1] . $storeHoursNotesMatch[2]);
            }
            
            $pattern = '#itemprop=\'(telephone|faxNumber)\'\s*href=\'([^\']+?)\'#';
            if (preg_match_all($pattern, $page, $storeContactMatches))
            {
                $aContact = array_combine($storeContactMatches[1], $storeContactMatches[2]);
            }
            
            $pattern = '#<ul class=\'content-text unordered-list\'>(.+?)</ul#';
            if (preg_match($pattern, $page, $serviceListMatch))
            {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                $strService = '';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches))
                {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (strlen($strService . $singleService) > 500) {
                            break;
                        }
                        if (strlen($strService))
                        {
                            $strService .= ', ';
                        }
                        $strService .= $singleService;
                    }
                }
            }
                        
            $eStore->setAddress($aAddress['streetAddress'], $aAddress['postalCode'] . ' ' . $aAddress['addressLocality'])
                    ->setStoreHoursNormalized(implode(',', $storeHoursMatches[2]) . ',' . implode(',', $storeHoursMatches[3]))
                    ->setPhoneNormalized($aContact['telephone'])
                    ->setFaxNormalized($aContact['faxNumber'])
                    ->setService($strServices);
            
        $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}