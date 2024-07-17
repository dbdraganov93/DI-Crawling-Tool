<?php

/**
 * Storecrawler für Osco (ID: 71387)
 */
class Crawler_Company_Osco_Store extends Crawler_Generic_Company
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
        $baseUrl = 'http://www.osco-shop.de';
        $storeFinderUrl = $baseUrl . '/StoreLocator/search?byname=&catFilter=&distance=0&lat=0&lng=0';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $sPage->open($storeFinderUrl);
        $page = $sPage->getPage()->getResponseBody();
      
        if (!preg_match_all('#<div[^>]*data-id="([^"]+)">(.+?)</div>\s*<script#is', $page, $matchStores)){
            throw new Exception($companyId . ': cannot find any stores: ' . $storeFinderUrl);
        }
  
        foreach ($matchStores[0] as $idx => $store){
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($matchStores[1][$idx]);
            
            $storeText = $matchStores[2][$idx];
            
            if (preg_match('#<h2[^>]*>(.+?)</h2>#', $storeText, $matchTitle)){
                $eStore->setSubtitle($matchTitle[1]);
            }
            
            if (preg_match('#<a[^>]*href="(http[^"]+)"#', $storeText, $matchLink)){
                $eStore->setWebsite($matchLink[1]);
            }
            
            if (preg_match('#<img[^>]*src="(http[^"]+)"#', $storeText, $matchImage)){
                $eStore->setImage($matchImage[1]);
            }
            
            if (preg_match('#<span[^>]*class="emailaddress"[^>]*>(.+?)</span>#', $storeText, $matchMail)){
                $eStore->setEmail($matchMail[1]);
            }
            
            if (preg_match('#zeiten:(.+?)</p>#', $storeText, $matchHours)){
                $eStore->setStoreHours($sOpenings->generateMjOpenings($matchHours[1]));
            }
            
            
            
            if (preg_match('#/h2>\s*<p[^>]*>(.+?)(<span|</p)#', $storeText, $matchAddress)){
                $addressParts = preg_split('#<br[^>]*>#', $matchAddress[1]);
                
                $eStore->setStreet($sAddress->extractAddressPart('street', $addressParts[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressParts[0]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressParts[1]))
                        ->setCity($sAddress->extractAddressPart('city', $addressParts[1]))
                        ->setPhone($sAddress->normalizePhoneNumber($addressParts[4]));
            } 
            
            $cStore->addElement($eStore);            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}