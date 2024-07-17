<?php

/**
 * Store Crawler für Stop and Go (ID: 28943)
 */
class Crawler_Company_StopAndGo_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.stopandgo.de/';
        $searchUrl = $baseUrl . 'standorte.html/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception ($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
                   
        $pattern = '#href="/(standorte/stop-go.+?)"#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStore) {
            $searchUrl = $baseUrl . $singleStore;
            if (!$sPage->open($searchUrl)) {
                $this->_logger->err($companyId . ': unable to open page for url ' . $searchUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="ym-gbox[^>]*custom_standortinfo"[^>]*>(.+?)</div>\s*</div>\s*</div>#';
            if (!preg_match($pattern, $page, $storeMatch)) {
                $this->_logger->err($companyId . ': unable to get store info for url ' . $searchUrl);
                continue;
            }
            
            $pattern = '#<p[^>]*>(.+?)</p>#';
            if (!preg_match_all($pattern, $storeMatch[1], $storeDetailMatches)) {
                $this->_logger->err($companyId . ': unable to get store details for url ' . $searchUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatches[1][0]);
            $aCalls = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatches[1][1]);
            
            $pattern = '#var\s*addy_text.+?=\s*(.+?);#';
            if (preg_match($pattern, $storeDetailMatches[1][2], $mailMatch)) {
                $eStore->setEmail(preg_replace(array('#\'#', '#\s*\+\s*#'), array('',''), $mailMatch[1]));
            }
            
            $eStore->setStreet(preg_replace('#(\s*(\/|,).+)#', '', $sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber(preg_replace('#(\s*(\/|,).+)#', '', $sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aCalls[0]))
                    ->setFax($sAddress->normalizePhoneNumber($aCalls[1]))
                    ->setStoreHours($sTimes->generateMjOpenings($storeDetailMatches[1][3]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}