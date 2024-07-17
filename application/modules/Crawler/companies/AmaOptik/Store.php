<?php

/* 
 * Store Crawler für AMA-Optik (ID: 29060)
 */
class Crawler_Company_AmaOptik_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'http://www.ama-optik.de';
        $searchUrl = '/wp-content/plugins/store-locator/sl-xml.php';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl . $searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $aStoresOrdered = array();
        $array = preg_split('#<marker#', $page);
        foreach ($array as $value) {
            $tmp = array();
            preg_match_all('#\s(\w+?)=\"([^\"]+?)\"#', $value, $machtes);
            foreach ($machtes[2] as $key => $value) {
                $tmp[$machtes[1][$key]] = $value;
            }
            $aStoresOrdered[] = $tmp;
        }

        foreach ($aStoresOrdered as $aSingleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            if (array_key_exists('name', $aSingleStore)) {
                $eStore->setSubtitle($aSingleStore['name']);
            }

            if (array_key_exists('street', $aSingleStore)) {
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aSingleStore['street'])));
            }

            if (array_key_exists('street', $aSingleStore)) {
                $eStore->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aSingleStore['street'])));
            }

            if (array_key_exists('city', $aSingleStore)) {
                $eStore->setCity($sAddress->normalizeCity($aSingleStore['city']));
            }

            if (array_key_exists('zip', $aSingleStore)) {
                $eStore->setZipcode($aSingleStore['zip']);
            }

            if (array_key_exists('lat', $aSingleStore)) {
                $eStore->setLatitude($aSingleStore['lat']);
            }

            if (array_key_exists('lng', $aSingleStore)) {
                $eStore->setLongitude($aSingleStore['lng']);
            }

            if (array_key_exists('url', $aSingleStore)) {
                if (!preg_match('#@#', $aSingleStore['url'])) {
                    $eStore->setWebsite(preg_replace(array('#\.\.#', '#,#', '#http:\/www#'), array('.', '.', 'www'), $aSingleStore['url']));
                }
            }

            if (array_key_exists('phone', $aSingleStore)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($aSingleStore['phone']));
            }

            if (array_key_exists('fax', $aSingleStore)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($aSingleStore['fax']));
            }

            if (array_key_exists('email', $aSingleStore)) {
                if (!preg_match('#anrufen#', $aSingleStore['email'])) {
                    $eStore->setEmail($aSingleStore['email']);
                }
            }

            if (array_key_exists('description', $aSingleStore)) {
                $eStore->setText($aSingleStore['description']);
            }

            if ($eStore->getZipcode() == 65719 && preg_match('#wilhelm#i', $eStore->getStreet())){
                $eStore->setStreetAndStreetNumber('Lorsbacher Straße 1a');
            }            
            
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
