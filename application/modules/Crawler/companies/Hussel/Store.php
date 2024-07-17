<?php

/**
 * Standortcrawler für Hussel (ID: 22391)
 */
class Crawler_Company_Hussel_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://unternehmen.hussel.de/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&lat=45&lng=10&autoload=1';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#Germany#', $singleJStore->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStreetAndStreetNumber($singleJStore->address)
                    ->setStoreNumber($singleJStore->id)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->zip)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setFaxNormalized($singleJStore->fax)
                    ->setEmail($singleJStore->email)
                    ->setStoreHoursNormalized($singleJStore->hours)
                    ->setWebsite($singleJStore->url);
            
            if (!preg_match('#^0#', $eStore->getFax())) {
                $eStore->setFax('0' . $eStore->getFax());
            }
            
            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
