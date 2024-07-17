<?php

/*
 * Store Crawler für Mode Express (ID: 29114)
 */

class Crawler_Company_ModeExpress_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.modeexpress-online.de/';
        $searchUrl = $baseUrl . 'files/MoEx/moex_filialsuche/phpsqlsearch_genxml.php?lat=50&lng=10&radius=1000';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<marker\s*(name[^>]+?)\/>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#\s*([^\="]+?)="([^"]+?)"#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStore);
                continue;
            }
            
            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setAddress($aInfos['street'], $aInfos['address'])
                    ->setStoreHoursNormalized('Mo-Fr ' . $aInfos['oeff_wo'] . ',Sa ' . $aInfos['oeff_we'])
                    ->setPhoneNormalized($aInfos['tele'])
                    ->setLatitude($aInfos['lat'])
                    ->setLongitude($aInfos['lng']);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
