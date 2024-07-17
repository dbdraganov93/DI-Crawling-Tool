<?php

/*
 * Store Crawler für AEZ (ID: 71882)
 */

class Crawler_Company_Aez_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.aez.de/';
        $searchUrl = $baseUrl . '09_cnt_01.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<td[^>]*>\s*<table[^>]*width="894"[^>]*>\s*<tr[^>]*>(.+?)</table#';
        if (!preg_match($pattern, $page, $storeTableMatch)) {
            throw new Exception ($companyId . ': unable to get store table.');
        }
        
        $pattern = '#<td[^>]*>(.+?\d{5}.+?</span>)\s*</p>\s*</td>#s';
        if (!preg_match_all($pattern, $storeTableMatch[1], $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from table.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<span[^>]*class="text"[^>]*>\s*(.+?)\s*</span#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStore);
            }
            
            $strTimes = '';
            for ($i = 0; $i < count($infoMatches[1]); $i++) {
                if (preg_match('#Marktleiter#', $infoMatches[1][$i])) {
                    $eStore->setText('Marktleiter: ' . $infoMatches[1][$i + 1]);
                }
                
                if (preg_match('#^\d{5}#', $infoMatches[1][$i])){
                    $eStore->setAddress($infoMatches[1][$i - 1], $infoMatches[1][$i]);
                }
                
                if (preg_match('#Telefon#', $infoMatches[1][$i])) {
                    $eStore->setPhoneNormalized($infoMatches[1][$i]);
                }
                
                if (preg_match('#Telefax#', $infoMatches[1][$i])) {
                    $eStore->setFaxNormalized($infoMatches[1][$i]);
                }
                
                if (preg_match('#>\s*([^<]+?\@[^<]+?)\s*<#', $infoMatches[1][$i], $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }
                
                if (preg_match('#Uhr#', $infoMatches[1][$i])) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $infoMatches[1][$i];
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
