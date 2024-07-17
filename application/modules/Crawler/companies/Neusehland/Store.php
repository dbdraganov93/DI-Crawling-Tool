<?php

/**
 * Storecrawler für Neusehland (ID: 71121)
 */
class Crawler_Company_Neusehland_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.neusehland.de';
        $storeFinderUrl = $baseUrl . '/ueber-uns/neusehland-in-ihrer-naehe.html';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $sPage->open($storeFinderUrl);
        $page = $sPage->getPage()->getResponseBody();
      
        if (!preg_match_all('#<a[^>]*href="(ueber-uns/neusehland-in-ihrer-naehe/[^"]+\.html)">(.+?)</a>#', $page, $matchLinks)){
            throw new Exception($companyId . ': cannot find any stores links: ' . $storeFinderUrl);
        }

        foreach ($matchLinks[1] as $idx => $store){
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setSubtitle($matchLinks[2][$idx]);
            
            $sPage->open($baseUrl . '/' . $store);
            $page = $sPage->getPage()->getResponseBody();
            
            if (preg_match('#<ul[^>]*id="imageslider"[^>]*>(.+?)</ul>#', $page, $imageMatch)){
                if (preg_match_all('#<img[^>]*src="([^"]+)"#', $imageMatch[1], $imageListMatch)){
                    $images = array();
                    foreach ($imageListMatch[1] as $image){
                        $images[] = $baseUrl . '/' . $image;
                    }
                    
                    $images = array_slice($images, 0, 5);
                    
                    $eStore->setImage(implode(',', $images));
                }
            }

            if (preg_match('#<p[^>]*>.+?<br[^>]*><br[^>]*>(.+?)</p>#', $page, $textMatch)){
                $eStore->setText($textMatch[1]);
            }            
            
            if (preg_match('#<h2[^>]*>Öffnungszeiten</h2>.*?<div[^>]*>(.+?)</div>#is', $page, $hoursMatch)){
                $eStore->setStoreHours($sOpenings->generateMjOpenings($hoursMatch[1]));
            }
             
            if (preg_match('#<h2[^>]*>Neusehland[^<]*</h2>[^<]*<div[^>]*class="text"[^>]*>.*?<p>([^<]+)<(br|p)[^>]*>(.*?)<(br|p)[^>]*>(.+?)</div>#is', $page, $addressMatch)){
                $eStore->setStreet($sAddress->extractAddressPart('street', strip_tags($addressMatch[1])))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', strip_tags($addressMatch[1])))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', strip_tags($addressMatch[3])))
                        ->setCity($sAddress->extractAddressPart('city', strip_tags($addressMatch[3])));                
            }

            if (preg_match('#Augenoptik:([^<]+)<#is', $page, $phoneMatch)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
                          
            $cStore->addElement($eStore, true);            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
