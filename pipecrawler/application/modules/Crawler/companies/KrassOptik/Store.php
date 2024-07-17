<?php

/**
 * Storecrawler für Krass Optik (ID: 29089)
 */
class Crawler_Company_KrassOptik_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.krass-optik.com/';
        $searchUrl = $baseUrl . 'Filialsuche.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*branches\s*=\s*\[(.+?),\]\s*</script#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#\[([^\]]+?)\]#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $singleStore = preg_replace('#\"#', '', $singleStore);
            $aInfos = preg_split('#\s*,\s*#', $singleStore);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($aInfos[0])
                    ->setLatitude($aInfos[1])
                    ->setLongitude($aInfos[2])
                    ->setStreetAndStreetNumber($aInfos[4])
                    ->setZipcode($aInfos[6])
                    ->setCity($aInfos[7])
                    ->setStoreHoursNormalized($aInfos[8])
                    ->setPhoneNormalized($aInfos[9])
                    ->setFaxNormalized($aInfos[10])
                    ->setEmail($aInfos[11])
                    ->setWebsite($aInfos[13]);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
