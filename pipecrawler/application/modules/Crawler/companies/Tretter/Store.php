<?php

/*
 * Store Crawler für Tretter Schuhe (ID: 67950)
 */

class Crawler_Company_Tretter_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tretter.com/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(https:\/\/www\.tretter\.com\/Filialen\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {

            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#KONTAKT(.+?)</div>\s*</div>#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get store info field: ' . $singleStoreUrl);
                continue;
            }

            $pattern = '#>([^<]+?)(\s*<[^>]*>\s*)*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $infoMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $infoMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Tel([^<]+?)<#';
            if (preg_match($pattern, $infoMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3])
                    ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
