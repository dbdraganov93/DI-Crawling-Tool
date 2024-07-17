<?php

/**
 * Crocs (ID: 69921)
 */
class Crawler_Company_Crocs_Store extends Crawler_Generic_Company {

    protected $_baseUrl = 'http://stores.crocs.com/ajax?&xml_request=%3Crequest%3E%3Cappkey%3EE6906E54-DD43-11E4-B6E0-BC5DF3F4F7A7%3C%2Fappkey%3E%3Cformdata+id%3D%22locatorsearch%22%3E%3Cdataview%3Estore_default%3C%2Fdataview%3E%3Corder%3Eicon+DESC%2C_distance%3C%2Forder%3E%3Climit%3E1000%3C%2Flimit%3E%3Cgeolocs%3E%3Cgeoloc%3E%3Caddressline%3EDresden+01279%3C%2Faddressline%3E%3Clongitude%3E%3C%2Flongitude%3E%3Clatitude%3E%3C%2Flatitude%3E%3Ccountry%3EDE%3C%2Fcountry%3E%3C%2Fgeoloc%3E%3C%2Fgeolocs%3E%3Csearchradius%3E1000%3C%2Fsearchradius%3E%3Cradiusuom%3Ekm%3C%2Fradiusuom%3E%3Cnobf%3E1%3C%2Fnobf%3E%3Cwhere%3E%3Ctblstorestatus%3E%3Cin%3EOpen%2COPEN%2Copen%3C%2Fin%3E%3C%2Ftblstorestatus%3E%3Cor%3E%3Ccrocsretail%3E%3Ceq%3E1%3C%2Feq%3E%3C%2Fcrocsretail%3E%3Ccrocsoutlet%3E%3Ceq%3E1%3C%2Feq%3E%3C%2Fcrocsoutlet%3E%3C%2For%3E%3C%2Fwhere%3E%3C%2Fformdata%3E%3C%2Frequest%3E';

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl)) {
            $logger->log('unable to open store-list page of company with id ' . $companyId, Zend_Log::CRIT);
        }

        $page = $sPage->getPage()->getResponseBody();
        $page = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $page);
        $pageXml = simplexml_load_string($page);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($pageXml->collection->poi as $xmlStore) {
            if ($xmlStore->country != 'DE') {
                continue;
            }

            if ($xmlStore->crocsretail != '1' && $xmlStore->crocsoutlet != '1') {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber((string) $xmlStore->address1);
            $eStore->setZipcode((string) $xmlStore->postalcode);
            $eStore->setCity((string) $xmlStore->city);
            $eStore->setLatitude((string) $xmlStore->latitude);
            $eStore->setLongitude((string) $xmlStore->longitude);
            $eStore->setSubtitle((string) $xmlStore->location);
            $eStore->setStoreNumber((string) $xmlStore->uid);
            $eStore->setPhoneNormalized((string) $xmlStore->phone);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
