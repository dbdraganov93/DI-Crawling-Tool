<?php

/**
 * Storecrawler für MarcoPolo (ID: 28824)
 *
 * Class Crawler_Company_MarcOPolo_Store
 */
class Crawler_Company_MarcOPolo_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        ini_set('memory_limit', '1G');
        $baseUrl = 'http://de.marc-o-polo.com/';
        $searchUrl = $baseUrl . 'stores'
                . '?group_id=4'
                . '&zip_city=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&country=DE'
                . '&range=all'
                . '&search=true';                                           
        
        $sPage = new Marktjagd_Service_Input_Page();     
        $page = $sPage->getPage();
        $page->setAlwaysHtmlDecode(false);
        $sPage->setPage($page);        
        
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $sGenerator = new Marktjagd_Service_Generator_Url();                                
        $cStore = new Marktjagd_Collection_Api_Store();
        
        $urls = $sGenerator->generateUrl($searchUrl, 'zip', 50);
        
        foreach ($urls as $url) {        
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();
            
            if (!preg_match('#<div[^>]*data-sdata="([^"]+)"#', $page, $dataMatch)){
                $this->_logger->info('store stores found for ' . $url);
            }                        
            
            $json = json_decode(html_entity_decode($dataMatch[1]));
                        
            foreach ($json->value as $store){
                if ($store->address->countryCode != 'DE') {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setSubtitle($store->name)
                        ->setLatitude((string) $store->geoLocation->latitude)
                        ->setLongitude((string) $store->geoLocation->longitude)
                        ->setZipcode($store->address->zip)
                        ->setCity($store->address->city)
                        ->setStreet($sAddress->extractAddressPart('street', $store->address->street))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $store->address->street))
                        ->setPhone($sAddress->normalizePhoneNumber($store->address->phone))
                        ->setFax($sAddress->normalizePhoneNumber($store->address->fax))
                        ->setWebsite($store->address->url)
                        ->setSection(html_entity_decode(implode(', ', $store->collections), ENT_COMPAT, 'UTF-8'))
                        ->setStoreHours($sOpenings->generateMjOpenings($store->openingHours));
                                                     
                $cStore->addElement($eStore);
            }
        }       
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}