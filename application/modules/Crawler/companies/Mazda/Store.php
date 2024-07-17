<?php
/**
 * Storecrawler für Mazda (ID: 68844)
 */
class Crawler_Company_Mazda_Store extends Crawler_Generic_Company
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
        $url = 'https://www.mazda.de/services/mazdacache.asmx/GetLocalDealersByInput';              
        $jPayload = "{ 'input':'[ZIP]', 'serviceType' : ''}";
        
        
        $sGeo = new Marktjagd_Database_Service_GeoRegion();        
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();
                
        $aZip = $sGeo->findZipCodesByNetSize(20);                        
        $client = new Zend_Http_Client($url);
        
        foreach($aZip as $zipcode){
            usleep(200 * 1000);
            $client->setRawData(str_replace('[ZIP]', $zipcode, $jPayload), 'application/json')->request('POST');        
            $aResponse = json_decode($client->getLastResponse()->getRawBody());
        
            foreach ($aResponse->d->Results as $result){                                                
                $eStore = new Marktjagd_Entity_Api_Store();                
                
                $eStore->setStoreNumber($result->Dealer->Id)
                        ->setWebsite($result->Dealer->WebsiteAddress)
                        ->setSubtitle($result->Dealer->Name)
                        ->setStreet($sAddress->extractAddressPart('street', $result->Dealer->LegacyData->Locations[0]->Address->FirstLine))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $result->Dealer->LegacyData->Locations[0]->Address->FirstLine))
                        ->setCity($result->Dealer->LegacyData->Locations[0]->Address->TownCity)
                        ->setZipcode($result->Dealer->LegacyData->Locations[0]->Address->PostCode)
                        ->setLatitude((string) $result->Dealer->LegacyData->Locations[0]->Address->Latitude)
                        ->setLongitude((string) $result->Dealer->LegacyData->Locations[0]->Address->Longitude)
                        ->setEmail($result->Dealer->LegacyData->EmailAddress)
                        ->setPhone($sAddress->normalizePhoneNumber($result->Dealer->LegacyData->Telephone))
                        ->setFax($sAddress->normalizePhoneNumber($result->Dealer->LegacyData->Fax));
                      
                if ($result->Dealer->LegacyData->Locations[0]->CanTestDrive){
                    $eStore->setText("Probefahrt bei diesem Händler möglich.");
                }                
                
                $cStore->addElement($eStore, true);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}