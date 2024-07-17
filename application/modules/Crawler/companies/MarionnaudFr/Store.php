<?php
/**
 * Store Crawler für Marionnaud FR (ID: 72363)
 */

class Crawler_Company_MarionnaudFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.marionnaud.fr/';
        $searchUrl = $baseUrl . 'magasins?q=85000&page=0';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $maxSite = ceil($jStores->total / 10);

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < $maxSite; $i++) {
            $searchUrl = $baseUrl . 'magasins?q=85000&page=' . $i;

            $sPage->open($searchUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores->data as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $aAddress = preg_split('#\s+#', $singleJStore->line1);
                $aFormattedAddress = array();
                $lineCount = 0;
                foreach ($aAddress as $singleValue) {
                    $aFormattedAddress[$singleValue] = $lineCount++;
                }

                $aFormattedAddress = array_flip($aFormattedAddress);
                $strAddress = implode(' ', $aFormattedAddress);
                if (preg_match('#^\d#', $strAddress)) {
                    $strAddress = $sAddress->extractAddressPart('street', $strAddress, 'fr') . ' ' . $sAddress->extractAddressPart('streetnumber', $strAddress, 'fr');
                }

                $strTimes = '';
                foreach ($singleJStore->openings as $day => $times) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= $day . ' ' . $times;
                }

                $eStore->setStoreNumber($singleJStore->name)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setStreetAndStreetNumber($strAddress)
                    ->setCity(ucwords(strtolower($singleJStore->town)))
                    ->setZipcode($singleJStore->postalCode)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreHoursNormalized($strTimes, 'text', TRUE, 'fr');

                if (count($singleJStore->features)) {
                    $eStore->setService(implode(', ', $singleJStore->features));
                }

                $cStores->addElement($eStore, TRUE);

            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}