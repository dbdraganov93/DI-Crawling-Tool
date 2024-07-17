<?php

/**
 * Store Crawler für Eterna (ID: 68873)
 */
class Crawler_Company_Eterna_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.eterna.de/';
        $searchUrl = $baseUrl . 'custom/index/sCustom/25';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<tbody[^>]*>(.+?)</tbody>#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<tr[^>]*>(.+?)</tr>#s';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores for list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<td[^>]*>\s*(.+?)\s*</td>#';
            if (!preg_match_all($pattern, $singleStore, $storeDetailMatches)) {
                $this->_logger->err($companyId . ': unable to get store details for ' . $singleStore);
                continue;
            }
            
            if (preg_match('#schweiz#i', $storeDetailMatches[1][1])) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setZipcode($storeDetailMatches[1][0])
                    ->setCity($storeDetailMatches[1][1])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', strip_tags($storeDetailMatches[1][2]))))
                    ->setStreetNumber(preg_replace(array('#(\s*\/.+)#', '#(\s*\(.+?\))#'), array('', ''), strip_tags($sAddress->extractAddressPart('streetnumber', $storeDetailMatches[1][2]))))
                    ->setPhone($sAddress->normalizePhoneNumber($storeDetailMatches[1][count($storeDetailMatches[1])-2]))
                    ->setStoreHours($sTimes->generateMjOpenings($storeDetailMatches[1][count($storeDetailMatches[1])-1]));
            
            if (!preg_match('#eterna\s*fachgeschäft#i', $storeDetailMatches[1][3])) {
                $eStore->setSubtitle(trim(strip_tags($storeDetailMatches[1][3])));
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}