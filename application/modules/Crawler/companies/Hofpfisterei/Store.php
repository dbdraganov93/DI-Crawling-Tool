<?php

/*
 * Store Crawler für Hofpfisterei (ID: 68925)
 */

class Crawler_Company_Hofpfisterei_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.hofpfisterei.de/';
        $searchUrl = $baseUrl . 'phpsqlsearch_genxml.php?lat=48.138341&lng=11.575555000000008&radius=1000';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $xmlStores = simplexml_load_string($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores as $singleXmlStore) {
            if (!preg_match('#filiale#', $singleXmlStore->attributes()->ftyp)) {
                continue;
            }
            $aAddress = preg_split('#\s*<[^>]*>\s*#', $singleXmlStore->attributes()->fulladdress);

            $eStore = new Marktjagd_Entity_Api_Store();
            for ($i = 0; $i < count($aAddress); $i++) {
                if (preg_match('#^\d{5}#', $aAddress[$i])) {
                    $eStore->setAddress($aAddress[$i - 1], $aAddress[$i]);
                    continue;
                }

                if (preg_match('#tel#i', $aAddress[$i])) {
                    $eStore->setPhoneNormalized($aAddress[$i]);
                    continue;
                }
            }
            
            $strTimes = $singleXmlStore->attributes()->open1 . ', ' . $singleXmlStore->attributes()->open2;

            if (strlen($singleXmlStore->attributes()->lunchbreak)
                    && preg_match('#([^\d]{2})\s*-?\s*([^\d]{2})?\s+(\d{1,2})\s*-\s*(\d{1,2})#', $singleXmlStore->attributes()->lunchbreak, $breakMatch)) {
                if (preg_match('#([^\d]{2})\s*-?\s*([^\d]{2})?\s+(\d{1,2}\.\d{2})\s*-\s*(.+)#', $singleXmlStore->attributes()->open1, $weekOneMatch)) {
                    $strTimes .= ',' . $breakMatch[1] . '-' . $breakMatch[2] . ' ' . $weekOneMatch[3] . '-' . $breakMatch[3];
                    $strTimes .= ',' . $breakMatch[1] . '-' . $breakMatch[2] . ' ' . $breakMatch[4] . '-' . $weekOneMatch[4];
                }
            }

            $eStore->setLatitude((string)$singleXmlStore->attributes()->lat)
                    ->setLongitude((string)$singleXmlStore->attributes()->lng)
                    ->setStoreNumber((string)$singleXmlStore->attributes()->fid)
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
