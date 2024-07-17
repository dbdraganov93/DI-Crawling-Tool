<?php

/**
 * Storecrawler für Wolford (ID: 69948)
 */
class Crawler_Company_Wolford_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $storeUrl = 'http://www.wolford.com/api/v1/boutiques/list?format=json';
        $sPage = new Marktjagd_Service_Input_Page();

        if(!$sPage->open($storeUrl)) {
            throw new Exception($companyId . ': unable to get store-list-page from url '
                    . $storeUrl);
        }

        $page = $sPage->getPage()->getResponseBody();
        $jStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();

        foreach ($jStores as $jStore) {
            if ($jStore->country != 'DE') {
                continue;
            }
            $eStore =  new Marktjagd_Entity_Api_Store();

            $eStore ->setStoreNumber($jStore->ID)
                    ->setStreet($mjAddress->extractAddressPart('street', $jStore->address))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $jStore->address))
                    ->setZipcode($jStore->plz)
                    ->setCity($jStore->city)
                    ->setPhone($mjAddress->normalizePhoneNumber($jStore->tel))
                    ->setFax($mjAddress->normalizePhoneNumber($jStore->fax))
                    ->setLatitude($jStore->geo->coordinates[0])
                    ->setLongitude($jStore->geo->coordinates[1]);

            switch ($jStore->category){
                case 'WBo': $eStore->setSubtitle('Boutique');
                    break;
                case 'FO': $eStore->setSubtitle('Outlet');
                    break;
                case 'WBp': $eStore->setSubtitle('Partner Boutique');
                    break;
                case 'Coaff': $eStore->setSubtitle('Partner Boutique');
            }


            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}