<?php

/*
 * Store Crawler für Lucky Bike (ID: 71181)
 */

class Crawler_Company_LuckyBike_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.lucky-bike.de/';
        $searchUrl = $baseUrl . 'index.php?cl=dd_standortfinder';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();


        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>Filiale ansehen#s';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($baseUrl . $singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div class="dd-storefinder-header">.+?</div>\s*<p>(.+?)</p>#s';
            if (!preg_match($pattern, $page, $storeContactMatch))
            {
                $this->_logger->err($companyId . ': unable to get store contact infos: ' . $singleStoreUrl);
                continue;
            }
            
            $aContact = preg_split('#\s*<[^>]*>\s*#', $storeContactMatch[1]);
            
            for($i = 0; $i < count($aContact); $i++)
            {
                $pattern = '#^\d{5}#';
                if (preg_match($pattern, $aContact[$i]))
                {
                    $eStore->setAddress($aContact[$i - 1], $aContact[$i]);
                    continue;
                }
            }
            
            $pattern = '#mailto:([^"]+?lucky-bike\.de)"#';
            if (preg_match($pattern, $storeContactMatch[1], $mailMatch))
            {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#tel:([^"]+)"#';
            if (preg_match($pattern, $storeContactMatch[1], $phoneMatch))
            {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#s';
            if (preg_match($pattern, $page, $storeHoursMatch))
            {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setWebsite($singleStoreUrl);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
