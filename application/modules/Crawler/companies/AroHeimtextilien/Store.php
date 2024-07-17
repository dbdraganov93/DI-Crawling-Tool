<?php

/* 
 * Store Crawler für Aro Heimtextilien (ID: 68946)
 */

class Crawler_Company_AroHeimtextilien_Store extends Crawler_Generic_Company
{
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.aro.de/';
        $searchUrl = $baseUrl . 'filialen/?/front/get/'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '/'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if (array_key_exists('error', $jStores)) {
                continue;
            }
            
            foreach ($jStores as $singleJStore) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li#';
                if (!preg_match_all($pattern, $singleJStore->display, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get store infos: ' . $singleJStore);
                    continue;
                }
                                
                $pattern = '#>([^<]+?)\s+[^\s]+?\s+(\d{5})#';
                if (!preg_match($pattern, $infoMatches[1][1], $cityZipMatch)) {
                    $this->_logger->err($companyId . ': unable to get store city and zip from info list: ' . $singleJStore);
                    continue;
                }
                
                $pattern = '#^([^<]+?)<#';
                if (!preg_match($pattern, $infoMatches[1][1], $streetMatch)) {
                    $this->_logger->err($companyId . ': unable to get store street from info list: ' . $singleJStore);
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#>\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $infoMatches[1][4], $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }
                
                $eStore->setCity($cityZipMatch[1])
                        ->setZipcode($cityZipMatch[2])
                        ->setPhoneNormalized($infoMatches[1][3])
                        ->setStreetAndStreetNumber($streetMatch[1])
                        ->setStoreHoursNormalized('Mo-Fr 09:00-19:00, Sa 09:00-16:00');
                
                $cStores->addElement($eStore);
            }
            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}