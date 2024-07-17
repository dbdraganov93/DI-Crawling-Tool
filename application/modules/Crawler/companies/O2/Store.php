<?php

/**
 * Standortcrawler für O2 (ID: 333)
 *
 * Class Crawler_Company_O2_Store
 */
class Crawler_Company_O2_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://www.o2online.de/';
        $searchUrl = $baseUrl . 'eshop/rest/shops?zip=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&city=&street='
                . '&range=200'
                . '&maxrows=500'
                . '&premiumStore=false'
                . '&soHoCompetency=false'
                . '&guru=false'
                . '&o2shops=false';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStores = new Marktjagd_Collection_Api_Store();

        $aUrls = $sGenerator->generateUrl($searchUrl, 'zipcode', 50);

        foreach ($aUrls as $url) {
            $this->_logger->info('open url: ' . $url);
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();
            $oStores = json_decode($page);
            foreach ($oStores->shops as $shop) {                
                // Doppelte Standorte überspringen
                $aStoreSkip = array(
                    '19001006' => '19001006',
                    '13200525' => '13200525',
                    '19000652' => '19000652',
                    '13200011' => '13200011',
                    '13200012' => '13200012',
                    '13200491' => '13200491',
                    '19001076' => '19001076',
                    '19001077' => '19001077',
                );

                if (array_key_exists($shop->dealerId, $aStoreSkip)) {
                    $this->_logger->info('skipped store ' . $shop->dealerId . ' (duplicate store)');
                    continue;
                }
                
                $strTime = 'Mo ' . $shop->openingHoursMonday1
                . ', Mo ' . $shop->openingHoursMonday2
                . ', Di ' . $shop->openingHoursTuesday1
                . ', Di ' . $shop->openingHoursTuesday2
                . ', Mi ' . $shop->openingHoursWednesday1
                . ', Mi ' . $shop->openingHoursWednesday2
                . ', Do ' . $shop->openingHoursThursday1
                . ', Do ' . $shop->openingHoursThursday2
                . ', Fr ' . $shop->openingHoursFriday1
                . ', Fr ' . $shop->openingHoursFriday2
                . ', Sa ' . $shop->openingHoursSaturday1
                . ', Sa ' . $shop->openingHoursSaturday2
                . ', So ' . $shop->openingHoursSunday1
                . ', So ' . $shop->openingHoursSunday2;

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($shop->dealerId)
                        ->setStreet($sAddress->extractAddressPart($sAddress::$EXTRACT_STREET, $shop->street))
                        ->setStreetNumber($sAddress->extractAddressPart($sAddress::$EXTRACT_STREET_NR, $shop->street))
                        ->setZipcode($shop->zip)
                        ->setCity($shop->city)
                        ->setLatitude($shop->latitude)
                        ->setLongitude($shop->longitude)
                        ->setStoreHoursNormalized($strTime);

                if ($shop->parkingQuantity > 0) {
                    $eStore->setParking($shop->parkingQuantity . ' Parkplätze');
                }

                if (strlen($shop->phone) && strlen($shop->phonePrefix)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($shop->phonePrefix . $shop->phone));
                }

                if (strlen($shop->fax) && strlen($shop->faxPrefix)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($shop->faxPrefix . $shop->fax));
                }

                if (strlen($shop->name)) {
                    $eStore->setSubtitle($shop->name);
                }

                if (strlen($shop->nameAdditional)) {
                    $subtitle = $eStore->getSubtitle();
                    if (strlen($subtitle)) {
                        $subtitle .= ', ';
                    }

                    $subtitle .= $shop->nameAdditional;
                    $eStore->setSubtitle($subtitle);
                }

                // Fexcom Standorte nicht aufnehmen (bereits in einem anderem Unternehmen vorhanden)
                if (preg_match('#fexcom#i', $eStore->getSubtitle())) {
                    continue;
                }

                if ($eStore->getStoreNumber() == 13200364){
                    $eStore->setStoreHoursNormalized('Mo-Fr 09:00-19:00, Sa 09:00-17:00');                    
                }                
                
                $cStores->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
