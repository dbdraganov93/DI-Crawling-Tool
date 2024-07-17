<?php

/**
 * Storecrawler für AnikaSchuh (ID: 67956)
 *
 * Class Crawler_Company_AnikaSchuh_Store
 */
class Crawler_Company_AnikaSchuh_Store extends Crawler_Generic_Company {

    protected $_baseUrl = 'http://www.anika-schuh.de/anika/filialsuche/';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl)) {
            throw new Exception('unable to get store-list for company with id ' . $companyId);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s+mapdata\s*=\s*(.+?\});#i';
        if (!preg_match($pattern, $page, $aStoreMatch)) {
            throw new Exception('unable to get stores for company with id ' . $companyId);
        }

        $jStores = json_decode($aStoreMatch[1], true);

        if (!$jStores){
            throw new Exception('unable to get decode json from page ' . $companyId);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        // alle Dateilseiten öffnen und Standortdaten ermitteln
        foreach ($jStores as $jStore) {
            if (!(is_array($jStore) && is_array($jStore[0]))){
                continue;
            }

            foreach ($jStore as $storeObj){
                // nur die reinen Anika-Filialen aufnehmen
                if (!preg_match('#anika#i', $storeObj['title'])){
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $address = preg_split('#,\s*#', $storeObj['address']);

                $eStore->setStreet($sAddress->extractAddressPart('street', $address[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $address[0]))
                        ->setCity($sAddress->extractAddressPart('city', $address[count($address)-2]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $address[count($address)-2]))
                        ->setLatitude($storeObj['point']['lat'])
                        ->setLongitude($storeObj['point']['lng'])
                        ->setSubtitle($storeObj['title']);

                $eStore->setStoreNumber($eStore->getLatitude() . ':' . $eStore->getLongitude());

                // fix für kaputte Standorte (Prenzlau) -  Adresse kann nur aus HTML ermittelt werden
                if (!strlen($eStore->getZipcode()) || !strlen($eStore->getCity())){
                    $address = preg_split('#</p>#', preg_replace('#<p>|\n#', '', $storeObj['body']));

                    $eStore->setStreet($sAddress->extractAddressPart('street', $address[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $address[1]))
                        ->setCity($sAddress->extractAddressPart('city', $address[2]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $address[2]));

                    if ($eStore->getStoreNumber() == '53.364262650348:13.093626238281') {
                        $eStore->setStreet('Strelitzer Straße')
                               ->setStreetNumber('25')
                               ->setZipcode('17235')
                               ->setCity('Neustrelitz');
                    }

                    if ($eStore->getStoreNumber() == '51.7920562:11.141448') {
                        $eStore->setStreet('Marktstraße')
                               ->setStreetNumber('03')
                               ->setZipcode('06484')
                               ->setCity('Quedlinburg');
                    }
                }

                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
