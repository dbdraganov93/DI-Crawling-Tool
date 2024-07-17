<?php

/*
 * Store Crawler für HTZ Heimtierzentrum (ID: 71905)
 */

class Crawler_Company_Htz_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.heimtierzentrum.de/';
        $searchUrl = $baseUrl . 'unserefachmaerkte-c-301-10.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="(htzfiliale[^\?]+?)\?#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls: ' . $searchUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            if (preg_match('#abmai#', $singleStoreUrl) && date('n') < 5) {
                continue;
            }
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="seitentitel"[^>]*>(.+?)</table#s';
            if (!preg_match($pattern, $page, $storeInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list for: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#>([^<]{5,}?)<#';
            if (!preg_match_all($pattern, $storeInfoListMatch[1], $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos for: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $strTimes = '';
            for ($i = 0; $i < count($storeInfoMatches[1]); $i++) {
                if (preg_match('#^\d{5}\s+[A-ZÄÖÜ]#', $storeInfoMatches[1][$i])) {
                    $eStore->setZipcodeAndCity($storeInfoMatches[1][$i])
                            ->setStreetAndStreetNumber($storeInfoMatches[1][$i - 1]);
                    if (preg_match('#^\(#', $eStore->getStreet())) {
                            $eStore->setStreetAndStreetNumber($storeInfoMatches[1][$i - 2]);
                    }
                    continue;
                }
                
                if (preg_match('#^tel#i', $storeInfoMatches[1][$i])) {
                    $eStore->setPhoneNormalized($storeInfoMatches[1][$i]);
                    continue;
                }
                
                if (preg_match('#^email:\s*(.+)#i', $storeInfoMatches[1][$i], $mailMatch)) {
                    $eStore->setEmail(preg_replace('#\(at\)#', '@', $mailMatch[1]));
                    continue;
                }
                
                if (preg_match('#\d{1,2}\s*:\d{2}\s*(-|bis)\s*\d{1,2}\s*:\d{2}#i', $storeInfoMatches[1][$i])) {
                    $strTimes .= $storeInfoMatches[1][$i] . ',';
                    continue;
                }
            }
            
            $eStore->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
