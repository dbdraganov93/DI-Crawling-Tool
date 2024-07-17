<?php
/**
 * Storecrawler für OptikerBode (ID: 69951)
 */
class Crawler_Company_OptikerBode_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     *
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl ($companyId)
    {
        $baseUrl = 'http://www.optiker-bode.de';     
        $searchUrl = $baseUrl . '/filial-suche';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();
        
        $weekdays = array ('1' => 'Mo', '2' => 'Di', '3' => 'Mi', '4' => 'Do', '5' => 'Fr', '6' => 'Sa', '7' => 'So');
        
        $sPage->open($searchUrl);                
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#jQuery\.extend\(Drupal\.settings\,\s*(\{\"basePath.+?)\)\;#', $page, $jsonMatch)) {
            throw new Exception('unable to get stores for company with id ' . $companyId);
        }
        
        $json = json_decode($jsonMatch[1]);
            
        foreach ($json->bod2s->nodes as $node){
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setSubtitle($node->field_store_address->und[0]->name_line)
                    ->setCity($node->field_store_address->und[0]->locality)
                    ->setZipcode($node->field_store_address->und[0]->postal_code)
                    ->setStreetAndStreetNumber($node->field_store_address->und[0]->thoroughfare)
                    ->setPhoneNormalized($node->field_store_address->und[0]->phone_number)
                    ->setFaxNormalized($node->field_store_address->und[0]->fax_number)
                    ->setLatitude($node->field_store_geolocation->und[0]->lat)
                    ->setLongitude($node->field_store_geolocation->und[0]->lng)
                    ->setEmail($node->field_store_email->und[0]->value)
                    ->setImage($node->field_store_hero_image->und[0]->value);
            
            $hoursAr = array();
            foreach ($node->field_store_business_hours->und as $jsonDay){
                $hoursAr[] = $weekdays[$jsonDay->day] . ' ' 
                        . preg_replace('#([0-9]{2})$#', ':$1', $jsonDay->starthours)
                        . '-'
                        . preg_replace('#([0-9]{2})$#', ':$1', $jsonDay->endhours);
            }
                                           
            $eStore->setStoreHoursNormalized(implode(',', $hoursAr));
            
            $cStore->addElement($eStore);    
        }            
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}