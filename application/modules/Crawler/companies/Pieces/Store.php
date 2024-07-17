<?php

class Crawler_Company_Pieces_Store extends Crawler_Generic_Company
{

    public function crawl($companyId) {
        $_baseUrl = 'http://de.pieces.com/on/demandware.store/Sites-DE-Site/de_DE'
                . '/StoreLocator-getJSON?param=stores%3Fcountry%3DDEU%26brand%3DACC'
                . '%26type%3Dretail&_=1389099443571';
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        if(!$sPage->open($_baseUrl)){
            $logger->log('unable to get stores of company with id ' . $companyId, Zend_Log::CRIT);
        }

        $page = $sPage->getPage()->getResponseBody();
        $aStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();

        foreach ($aStores as $aStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore ->setStoreNumber((string)$aStore->physicalId)
                    ->setSubtitle($aStore->name)
                    ->setCity($aStore->address->city)
                    ->setZipcode($aStore->address->postalCode)
                    ->setStreet($mjAddress->extractAddressPart('street', $aStore->address->street))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aStore->address->street))
                    ->setPhone($mjAddress->normalizePhoneNumber($aStore->address->phone));
            if (strlen($aStore->address->fax)) {
                $eStore->setFax('0' . $aStore->address->fax);
            }
            if ((string)$aStore->address->latitude != '0') {
                $eStore->setLatitude($aStore->address->latitude);
            }
            if ((string)$aStore->address->longitude != '0') {
                $eStore->setLongitude($aStore->address->longitude);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}