<?php

/* 
 * Store Crawler für Pizza Hut (ID: 70867)
 */

class Crawler_Company_PizzaHut_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.pizzahut.de/';
        $searchUrl = $baseUrl . 'restaurants-express/restaurantfinder/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*stores_data\s*=\s*([^;]+?);#s';
        if (!preg_match($pattern, $page, $storeListMatch))
        {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $jStores = json_decode($storeListMatch[1])->stores;
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreet($sAddress->extractAddressPart('street', $singleJStore->title))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->title))
                    ->setZipcode($singleJStore->zipcode)
                    ->setCity($singleJStore->city)
                    ->setLongitude($singleJStore->lon)
                    ->setLatitude($singleJStore->lat)
                    ->setWebsite($singleJStore->link);
            
            if (strlen($eStore->getWebsite()))
            {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#ffnungszeiten(.+?)</div>\s*</div#s';
                if (preg_match($pattern, $page, $storeHoursMatch))
                {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1], 'text', TRUE));
                }
                
                $pattern = '#service</h3>(.+?</div>)\s*</div>\s*</div#si';
                if (preg_match($pattern, $page, $serviceListMatch))
                {
                    $pattern = '#<div[^>]*class="title"[^>]*>\s*(.+?)\s*</div#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches))
                    {
                        $eStore->setService(trim(strip_tags(implode(', ' , $serviceMatches[1]))));
                    }
                }
                
                $pattern = '#phone_number"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $storePhoneMatch))
                {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($storePhoneMatch[1]));
                }
                
                $pattern = '#about_text"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $storeTextMatch))
                {
                    $eStore->setText($storeTextMatch[1]);
                }
                
            }
            
            if (preg_match('#neueröffnung\s*im#i', $eStore->getText()))
            {
                continue;
            }
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}