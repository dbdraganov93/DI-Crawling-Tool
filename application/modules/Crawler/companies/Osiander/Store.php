<?php

/* 
 * Store Crawler für Osiander (ID: 70949)
 */

class Crawler_Company_Osiander_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.osiander.de/';
        $searchUrl = $baseUrl . 'buchhandlungen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="\/buchhandlungen\/([^"]+?)"[^>]*>\s*zur\s*buchhandlung#i';
        if (!preg_match_all($pattern, $page, $storeUrlMatches))
        {
            throw new Exception($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl)
        {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#bookshopAdress[^>]*>\s*(.+?</a>\s*</p>)#s';
            if (!preg_match($pattern, $page, $storeAddressMatch))
            {
                throw new Exception ($companyId . ': unable to get store address list: ' . $storeDetailUrl);
            }
            
            $pattern = '#<p[^>]*>\s*(<[^>]*>)*\s*([^<]+?)<#';
            if (!preg_match_all($pattern, $storeAddressMatch[1], $storeAddressDetailMatches))
            {
                throw new Exception ($companyId . ': unable to get any store address infos from list: ' . $storeDetailUrl);
            }
            
            $pattern = '#branchtimes[^>]*>(.+?)</tbody#s';
            if (preg_match($pattern, $page, $storeHoursMatch))
            {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#class="iconBox"[^>]*>(.+?)</div#';
            if (preg_match($pattern, $page, $serviceListMatch))
            {
                $pattern = '#alt="([^"-]+?)\s*["|-]#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches))
                {
                    for ($i = 0; $i < count($serviceMatches[1]); $i++)
                    {
                        if (preg_match('#barrierefrei#i', $serviceMatches[1][$i]))
                        {
                            $eStore->setBarrierFree(TRUE);
                            unset($serviceMatches[1][$i]);
                            break;
                        }
                    }
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }
            
            $pattern = '#herzlich\s*willkommen[^<]+?</h1>\s*<p[^>]*>(.+?)</div#si';
            if (preg_match($pattern, $page, $storeTextMatch))
            {
                $eStore->setText(trim(strip_tags($storeTextMatch[1])));
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $storeAddressDetailMatches[2][1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddressDetailMatches[2][1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $storeAddressDetailMatches[2][2]))
                    ->setCity($sAddress->extractAddressPart('city', $storeAddressDetailMatches[2][2]))
                    ->setPhone($sAddress->normalizePhoneNumber($storeAddressDetailMatches[2][3]))
                    ->setEmail($sAddress->normalizeEmail($storeAddressDetailMatches[2][4]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}