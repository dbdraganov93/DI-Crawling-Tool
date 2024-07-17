<?php

/*
 * Store Crawler für Toyota (ID: 71193)
 */

class Crawler_Company_Toyota_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.toyota.de/';
        $searchUrl = $baseUrl . 'api/dealer/drive/10/45?count=10000';
        
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $jStores = json_decode(preg_replace(array('#\\\"#'), array(''), $result));

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->dealers as $singleJStore) {
            if (!preg_match('#^de#', $singleJStore->country)) {
                continue;
            }

            $strSections = '';
            foreach ($singleJStore->services as $singleSection) {
                if (strlen($strSections)) {
                    $strSections .= ', ';
                }
                $strSections .= $singleSection->label;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->id)
                    ->setStreetAndStreetNumber($singleJStore->address->address1)
                    ->setZipcode($singleJStore->address->zip)
                    ->setCity($singleJStore->address->city)
                    ->setLatitude($singleJStore->address->geo->lat)
                    ->setLongitude($singleJStore->address->geo->lon)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setFaxNormalized($singleJStore->fax1)
                    ->setEmail($singleJStore->eMail)
                    ->setWebsite($singleJStore->url)
                    ->setSection($strSections)
                    ->setSubtitle($singleJStore->operatingCompany->name);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
