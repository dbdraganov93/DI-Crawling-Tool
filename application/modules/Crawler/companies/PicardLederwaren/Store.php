<?php

/**
 * Picard Lederwaren
 *
 * Id: 70024
 *
 * Class Crawler_Company_PicardLederwaren_Store
 */
class Crawler_Company_PicardLederwaren_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {                
        $url = 'http://www.picard-lederwaren.de/out/picard_neu/src/onewidget/locationdata.js';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();
        
        
        
        if (!preg_match('#locations\s*=\s*(\[.*?\])\;#is', $page, $matchPage)) {
            $this->_logger->log('PICARD Lederwaren (ID: 70024) store crawler' . "\n"
                . 'couldn\'t get JSON String from url ' . $url,
                Zend_Log::CRIT);
        }
        $page = $matchPage[1];
        $jsonStores = json_decode($page);
     
        foreach ($jsonStores as $jsonStore) {
            if ($jsonStore->country != 'Germany') {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setSubTitle($jsonStore->title);

            if (strlen($eStore->getSubtitle())
                && strlen($jsonStore->title2)
            ) {
                $eStore->setSubtitle($eStore->getSubtitle() . ', ');
            }

            $eStore->setSubtitle($eStore->getSubtitle() . $jsonStore->title2);
            $aAddress = explode(';', $jsonStore->address);
            $street = $sAddress->extractAddressPart(Marktjagd_Service_Text_Address::$EXTRACT_STREET, $aAddress[0]);
            $streetNr = $sAddress->extractAddressPart(Marktjagd_Service_Text_Address::$EXTRACT_STREET_NR, $aAddress[0]);
            $sZipCity = preg_replace('#DE-#is', '', $aAddress[1]);
            $zip = $sAddress->extractAddressPart(Marktjagd_Service_Text_Address::$EXTRACT_ZIP, $sZipCity);
            $city = $sAddress->extractAddressPart(Marktjagd_Service_Text_Address::$EXTRACT_CITY, $sZipCity);

            $eStore->setStreet($street)
                   ->setStreetNumber($streetNr)
                   ->setZipcode($zip)
                   ->setCity($city);

            $aContact = explode(';', $jsonStore->contact);
            foreach ($aContact as $sContact) {
                if (preg_match('#Tel.:\s*(.*$)#is', $sContact, $matchTel)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($matchTel[1]));
                }

                if (preg_match('#Fax.:\s*(.*$)#is', $sContact, $matchFax)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($matchFax[1]));
                }

                if (preg_match('#(.*?@.*$)#', $sContact, $matchMail)) {
                    $eStore->setEmail($matchMail[1]);
                }

                if (preg_match('#(.*?www\..*$)#is', $sContact, $matchWebsite)) {
                    $eStore->setWebsite($matchWebsite[1]);
                }
            }

            $eStore->setLatitude($jsonStore->lat)
                   ->setLongitude($jsonStore->lng);

            /**
             * doppelte SO überspringen
             **/
            if ($eStore->getZipcode() == "35037"
                && $eStore->getSubtitle() == 'Kaufhaus Ahrens'
            ) {
                continue;
            }

            if ($eStore->getZipcode() == '95448'
                && ($eStore->getSubtitle() == 'Emil Kreher GmbH & Co.'
                    || $eStore->getSubtitle() == 'Lange Fashion & Design GmbH')
            ) {
                continue;
            }

            if ($eStore->getZipcode() == '65843'
                && $eStore->getSubtitle() == 'Karstadt Warenhaus GmbH, Hertie'
            ) {
                continue;
            }

            if ($eStore->getZipcode() == '53721'
                && $eStore->getSubtitle() == 'Schugt - Trends aus Leder, Lederwaren Gert Schugt GmbH'
            ) {
                continue;
            }

            if ($eStore->getZipcode() == '93059'
                && $eStore->getSubtitle() == 'Galeria Kaufhof, Donau-EKZ'
            ) {
                continue;
            }

            if ($eStore->getZipcode() == '6844'
                && $eStore->getSubtitle() == 'Karstadt Warenhaus GmbH'
            ) {
                continue;
            }

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}