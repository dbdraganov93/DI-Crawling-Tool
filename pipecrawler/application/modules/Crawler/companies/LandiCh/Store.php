<?php

/* 
 * Store Crawler für Landfuxx (ID: 29129)
 */

class Crawler_Company_LandiCh_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://landi.ch';
        $searchUrl = $baseUrl . '/places/api/search/standorte?lat1=44.11713651271858&lng1=3.9505284279584885'
            . '&lat2=49.38055458620972&lng2=12.497891709208488&q=&standortType=1&maxResults=999';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();


        foreach ($json->Places as $jsonStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setLatitude($jsonStore->LatLng->Latitude)
                    ->setLongitude($jsonStore->LatLng->Longitude)
                    ->setCity($jsonStore->Ort)
                    ->setZipcode($jsonStore->PLZ)
                    ->setStreetAndStreetNumber($jsonStore->Strasse, 'CH')
                    ->setStoreNumber($jsonStore->Id)
                    ->setWebsite($baseUrl . $jsonStore->Url);


            $eStore->setWebsite(
                str_replace(
                    array('â', 'ê'),
                    array('%C3%A2', '%C3%AA'),
                    $eStore->getWebsite()
                )
            );

            try {
                $sPage->open($eStore->getWebsite());
            } catch (Zend_Http_Client_Exception $e) {
                $sPage->open($eStore->getWebsite());
            } catch (Zend_Uri_Exception $exception) {
                $cStores->addElement($eStore);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            $qDetail = new Zend_Dom_Query($page, 'UTF-8');
            $nContact = $qDetail->query("div[class=\"place-details\"]");
            $sContact = $nContact->current()->c14n();
            if (preg_match('#tel\:([^"]+)"#', $sContact, $matchPhone)) {
                $eStore->setPhoneNormalized(str_replace('+41', '0', $matchPhone[1]));
            }

            if (preg_match('#mailto\:([^"]+)"#', $sContact, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }

            $nOpenings = $qDetail->query("div[class=\"place-openinghours\"]");
            $sOpenings = $nOpenings->current()->c14n();
            
            $eStore->setStoreHoursNormalized($sOpenings);

            $cStores->addElement($eStore);
        } // Ende Standorte
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}