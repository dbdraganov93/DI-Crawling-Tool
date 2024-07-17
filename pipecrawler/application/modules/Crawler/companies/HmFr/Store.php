<?php
/**
 * Store Crawler für H&M FR (ID: 72356)
 */

class Crawler_Company_HmFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://hm.storelocator.hm.com/';
        $searchUrl = $baseUrl . 'rest/storelocator/stores/1.0/locale/fr_FR/country/FR?_type=json';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson()->storesCompleteResponse->storesComplete->storeComplete;        
            
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#FR#', $singleJStore->countryCode)) {
                $this->_logger->info($companyId . ': not a french store.');
                continue;
            }

            $strSection = '';
            if (count($singleJStore->departmentsWithConcepts->departmentWithConcepts)) {
                foreach ($singleJStore->departmentsWithConcepts->departmentWithConcepts as $singleJSection) {
                    if (strlen($strSection)) {
                        $strSection .= ', ';
                    }

                    $strSection .= $singleJSection->name;
                }
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeId)
                ->setCity($singleJStore->city)
                ->setPhoneNormalized($singleJStore->phone)
                ->setLongitude($singleJStore->longitude)
                ->setLatitude($singleJStore->latitude)
                ->setSection($strSection);

            if (is_array($singleJStore->openingHours->openingHour)) {
                $eStore->setStoreHoursNormalized(implode(',', $singleJStore->openingHours->openingHour), 'text', TRUE, 'fr');
            } else {
                $eStore->setStoreHoursNormalized($singleJStore->openingHours->openingHour, 'text', TRUE, 'fr');
            }

            if (count($singleJStore->address->addressLine) < 2) {
                $eStore->setStreetAndStreetNumber($singleJStore->name, 'fr')
                    ->setZipcodeAndCity($singleJStore->address->addressLine);
            } else {
                $eStore->setStreetAndStreetNumber($singleJStore->address->addressLine[0], 'fr')
                    ->setZipcodeAndCity($singleJStore->address->addressLine[1]);
            }

            if ($eStore->getStoreNumber() == 'FR0196' || $eStore->getStoreNumber() == 'FR0215') {
                $eStore->setCity($singleJStore->city);
            }
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}