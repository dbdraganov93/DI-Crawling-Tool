<?php

/**
 * Storecrawler für san francisco coffee company (ID: 71385)
 */
class Crawler_Company_SanFranciscoCoffee_Store extends Crawler_Generic_Company
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
        $baseUrl = 'http://www.sfcc.de';
        $storeFinderUrl = $baseUrl . '/coffeeplaces';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $sPage->open($storeFinderUrl);
        $page = $sPage->getPage()->getResponseBody();
      
        if (!preg_match('#<div[^>]*class="moduletable">(.+?)</div>#is', $page, $matchTable)){
            throw new Exception($companyId . ': cannot find store table: ' . $storeFinderUrl);
        }
        
        if (!preg_match_all('#<a[^>]*href="([^"]+)"#', $matchTable[0], $matchStoreLinks)){
            throw new Exception($companyId . ': cannot find any store links: ' . $storeFinderUrl);
        }

        foreach ($matchStoreLinks[1] as $storeLink){
            $sPage->open($baseUrl . $storeLink);
            $page = $sPage->getPage()->getResponseBody();
   
            if (!preg_match('#Kartenansicht.*?<p[^>]*>(.{48,}?)</p>#', $page, $matchAdress)){
                $this->_logger->log('cannt find any store address: ' . $baseUrl . $storeLink, Logger::WARN);
            }
            
            $matchAdress[1] = preg_replace('#</p>#', '', $matchAdress[1]);
            $matchAdress[1] = preg_replace('#<p>#', '/', $matchAdress[1]);            
            
            $addressLines = preg_split('#<br[^>]*>#', $matchAdress[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setWebsite($baseUrl . $storeLink)
                    ->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                    ->setCity($sAddress->extractAddressPart('city', $addressLines[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($addressLines[2]));
                        
            if (preg_match('#zeiten:(.+?)</p>#', $page, $matchHours)){
                $eStore->setStoreHours($sOpenings->generateMjOpenings($matchHours[1]));
            } 
            
            $cStore->addElement($eStore, true);            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}