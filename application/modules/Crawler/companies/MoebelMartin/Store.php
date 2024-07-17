<?php

/* 
 * Store Crawler für Möbel Martin (ID: 79)
 */

class Crawler_Company_MoebelMartin_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.moebel-martin.de/';
        $searchUrl = $baseUrl . 'standorte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<article[^>]*>(.+?adresse.+?)</article#i';
        if (!preg_match_all($pattern, $page, $storeMatches))
        {
            throw new Exception ($companyId . ': unable to get any stores');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#adresse</h1>\s*<p[^>]*>(.+?)</p#si';
            if (!preg_match($pattern, $singleStore, $contactMatch))
            {
                $this->_logger->err($companyId . ': unable to get store address');
                continue;
            }
            
            $aContactInfos = preg_split('#\s*<br[^>]*>\s*#', $contactMatch[1]);
            $aAddress = preg_split('#\s*·\s*#', $aContactInfos[1]);
            $aContact = preg_split('#\s*·\s*#', $aContactInfos[2]);
            
            if (preg_match('#\s*\(([^\)]+)\)\s*$#', $aAddress[0], $subtitleMatch))
            {
                $eStore->setSubtitle($subtitleMatch[1]);
                $aAddress[0] = preg_replace('#\s*\(([^\)]+)\)\s*$#', '', $aAddress[0]);
            }
            
            $pattern = '#ffnungszeiten\s*</h1>(.+?)</p#i';
            if (preg_match($pattern, $singleStore, $storeHoursMatch))
            {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#sortiment\s*</h1>(.+?)</div#i';
            if (preg_match($pattern, $singleStore, $sectionListMatch))
            {
                $pattern = '#>([^<]+?)</a|title="([^"]+?)"#';
                if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches))
                {
                    $strSection = '';
                    foreach ($sectionMatches[1] as $singleSection)
                    {
                        if (strlen($singleSection))
                        {
                            if (strlen($strSection))
                            {
                                $strSection .= ', ';
                            }
                            $strSection .= $singleSection;
                        }
                    }
                    foreach ($sectionMatches[2] as $singleSection)
                    {
                        if (strlen($singleSection))
                        {
                            if (strlen($strSection))
                            {
                                $strSection .= ', ';
                            }
                            $strSection .= $singleSection;
                        }
                    }
                }
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aContact[0]))
                    ->setFax($sAddress->normalizePhoneNumber($aContact[1]))
                    ->setSection($strSection);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}