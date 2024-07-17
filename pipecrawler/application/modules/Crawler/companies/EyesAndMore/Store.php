<?php

/**
 * * Storecrawler für eyes+more (ID: 69977)

 *
 * Class Crawler_Company_EyesAndMore_Store
 */
class Crawler_Company_EyesAndMore_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'https://www.eyesandmore.de';
        $storeFinderUrl = $baseUrl . '/store-finder?q=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '&page=0';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $weekdays = array("Mo","Di","Mi","Do","Fr","Sa","So");
        
        $sUrls = $sGen->generateUrl($storeFinderUrl, 'zip', 80);
                
        foreach ($sUrls as $sUrl){
            $sPage->open($sUrl);
            $json = $sPage->getPage()->getResponseAsJson();
                              
            foreach ($json->data as $entry){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setSubtitle($entry->displayName)
                        ->setWebsite($baseUrl . $entry->url)
                        ->setPhoneNormalized($entry->phone)
                        ->setStreet($entry->line1)
                        ->setStreetNumber($entry->line2)
                        ->setCity($entry->town)
                        ->setZipcode($entry->postalCode)
                        ->setLatitude($entry->latitude)
                        ->setLongitude($entry->longitude)
                        ->setService(implode(', ', $entry->features));
                     
                $hoursAr = array();
                foreach ($weekdays as $weekday){
                    if ($entry->openings->$weekday && !preg_match('#schlossen#is', $entry->openings->$weekday)){
                        $hoursAr[] = $weekday . ' ' .  $entry->openings->$weekday;
                    }
                }                
                $eStore->setStoreHoursNormalized(implode(',', $hoursAr));
                
                $specialHoursAr = array();
                foreach ($entry->specialOpenings as $specialHourDay => $specialHourVal){
                    $specialHoursAr[] = $specialHourDay . ' ' . $specialHourVal;
                }
                $eStore->setStoreHoursNotes(implode(' / ', $specialHoursAr));
                
                Zend_Debug::dump($eStore);
                $cStores->addElement($eStore);
            }
        }       
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}