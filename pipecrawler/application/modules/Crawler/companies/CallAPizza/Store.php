<?php

/**
 * Store Crawler für Call-A-Pizza (ID: 29032)
 */
class Crawler_Company_CallAPizza_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.call-a-pizza.de';
        $searchUrl = $baseUrl . '/bestellen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<td[^>]*class="bestellen_address[^>]*>(.+?)<td[^>]*class="bestellen_storebtn#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos.');
                continue;
            }
            
            $aInfo = array_combine($infoMatches[1], $infoMatches[2]);
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>\s*([^<]+?tag|[^<]+?woch)\s*</div>\s*'
                    . '<div[^>]*class="bestellen_oph_right">\s*(.+?)\s*</div>#';
            if (preg_match_all($pattern, $singleStore, $storeHoursMatches)) {
                $strTimes = '';
                for ($i = 0; $i < count($storeHoursMatches[0]); $i++) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $storeHoursMatches[1][$i] . ' ' . $storeHoursMatches[2][$i];
                }
            }
            
            $eStore->setStreetAndStreetNumber($aInfo['streetAddress'])
                    ->setZipcode($aInfo['postalCode'])
                    ->setCity($aInfo['addressLocality'])
                    ->setPhoneNormalized($aInfo['telephone'])
                    ->setStoreHoursNormalized($strTimes, 'text', TRUE);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
