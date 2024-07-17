<?php

/*
 * Store Crawler für Getränke Schürmann (ID: 69544)
 */

class Crawler_Company_GetraenkeSchuermann_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.getraenkeschuermann.de/';
        $searchUrl = $baseUrl . 'de_DE/Getraenke-Oase/Getraenkemaerkte/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#(<h3[^>]*>.+?<hr>)#s';
        if (!preg_match_all($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeListMatch[1] as $singleStore) {
            if (!preg_match('#<a[^>]*href="([^"]+)"#is', $singleStore, $matchUrl)) {
                $this->_logger->err($companyId . ': unable to get store detail url.');
                continue;
            }

            $storeUrl = $matchUrl[1];
            $sPage->open($baseUrl . $storeUrl);

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setWebsite($baseUrl . $storeUrl);
            $detailPage = $sPage->getPage()->getResponseBody();

            $pattern = '#</h1>\s*<p>\s*(.*?)\s*<br>\s*<strong>\s*(.*?)\s*</strong>#';
            if (!preg_match($pattern, $detailPage, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $eStore->setAddress($storeAddressMatch[1], $storeAddressMatch[2]);
            
            $pattern = '#Telefon\:\s*(.*?)</p>#is';
            if (preg_match($pattern, $detailPage, $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }

            $pattern = '#Fax\:\s*(.*?)</p>#is';
            if (preg_match($pattern, $detailPage, $storeFaxMatch)) {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten\s*</strong>\s*<p>(.*?)</p>#';
            if (preg_match($pattern, $detailPage, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
