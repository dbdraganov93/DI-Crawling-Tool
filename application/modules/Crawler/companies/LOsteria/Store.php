<?php

/* 
 * Store Crawler für L'Osteria (ID: 71302)
 */

class Crawler_Company_LOsteria_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://losteria.de/';
        $searchUrl = $baseUrl . 'restaurants/deutschland/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="(http:\/\/losteria\.de\/restaurant\/[^\/]+?\/)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeUrlMatches[1]) as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#itemprop="([^"]+?)"[^>]*>([^<]+?)<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos.');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#itemprop="([^"]+?)"[^>]*datetime="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[2]));
            }
            
            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#</h[^>]*>(\s*</div>)?\s*<p[^>]*>([^<]+?)</p#';
            $strText = '';
            if (preg_match($pattern, $page, $textMatch)) {
                $strText = $textMatch[2];
                if (strlen($strText) > 1000) {
                    $strText = substr($textMatch[2], 0, 965);
                    $strText = substr($textMatch[2], 0, strrpos($strText, '.') + 1);
                }
                $eStore->setText($strText);
            }
            
            $eStore->setStreetAndStreetNumber(preg_replace('#\s*,\s*#', '', $aInfos['streetAddress']))
                    ->setZipcode($aInfos['postalCode'])
                    ->setCity($aInfos['addressLocality'])
                    ->setPhoneNormalized($aInfos['telephone'])
                    ->setFaxNormalized($aInfos['faxNumber']);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}