<?php

/*
 * Store Crawler für Streb Getränke (ID: 69545)
 */

class Crawler_Company_StrebGetraenke_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.streb-getraenke.de/';
        $searchUrl = $baseUrl . 'index.php?p=2100';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#onclick="Javascript:document\.location\.href=\'index\.php\?p=2100([^\']+?)\'#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<td[^>]*>\s*<img[^>]*src="bilder[^"]+?"[^>]*>(.+?)</table#';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>([^<]+?)<[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $storeInfoMatch[1], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</td>#';
            if (preg_match($pattern, $storeInfoMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#([A-Z][a-z])\s+([A-Z][a-z])#', '$1-$2', $storeHoursMatch[1]));
            }
            
            $pattern = '#tel:?([^<]+?)<#i';
            if (preg_match($pattern, $storeInfoMatch[1], $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }
            
            $pattern = '#fax:?([^<]+?)<#i';
            if (preg_match($pattern, $storeInfoMatch[1], $storeFaxMatch)) {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }
            
            $pattern = '#(leitung:?[^<]+?)<#i';
            if (preg_match($pattern, $storeInfoMatch[1], $storeTextMatch)) {
                $eStore->setText($storeTextMatch[1]);
            }
            
            $eStore->setAddress($storeAddressMatch[1], $storeAddressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
