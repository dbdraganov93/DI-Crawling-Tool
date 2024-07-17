<?php

/**
 * Storecrawler für Mexx (ID: 67876)
 */
class Crawler_Company_Mexx_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $storeUrl = 'http://www.mexx.com/de/service/store-finder';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($storeUrl);
        $htmlBody = $sPage->getPage()->getResponseBody();
        
        if (!preg_match('#window\.Stores\s*=\s*(\[\{.+?\}\]);#', $htmlBody, $match)){
            throw new Exception('unable to get json for company with id ' . $companyId);
        }
        
        $json = json_decode($match[1]);
        
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($json as $store) {
            if ($store->country != "Germany"){
                continue;
            }
                        
            $eStore = new Marktjagd_Entity_Api_Store();
 
            $eStore->setStoreNumber($store->store_id)
                    ->setSubtitle($store->name)
                    ->setStreet($sAddress->extractAddressPart('street', $store->address))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $store->address))
                    ->setZipcode(preg_replace('#[^0-9]#', '', $store->zipcode))
                    ->setCity($store->city)
                    ->setPhone($sAddress->normalizePhoneNumber($store->phone))
                    ->setLatitude($store->lat)
                    ->setLongitude($store->long);           

            $hours = '';
            
           if ($store->monday){
               $hours .= 'Mo ' . $store->monday . ',';
           }
           
           if ($store->tuesday){
               $hours .= 'Di ' . $store->tuesday . ',';
           }
           
           if ($store->wednesday){
               $hours .= 'Mi ' . $store->wednesday . ',';
           }
           
           if ($store->thursday){
               $hours .= 'Do ' . $store->thursday . ',';
           }
           
           if ($store->friday){
               $hours .= 'Fr ' . $store->friday . ',';
           }
           
           if ($store->saturday){
               $hours .= 'Sa ' . $store->saturday . ',';
           }
           
           if (preg_match('#[0-9]#',$store->sunday)){
               $hours .= 'So ' . $store->sunday . ',';
           }
           
            $eStore->setStoreHours(substr($hours, 0, strlen($hours)-1));
            
            $cStores->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, false);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
