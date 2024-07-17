<?php

/*
 * Store Crawler für Volg Ch (ID: 72147)
 */

class Crawler_Company_VolgCh_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.volg.ch/';
        $searchUrl = $baseUrl . 'modules/Volg/map/volgmap.json';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->objects->de as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            foreach ($singleJStore->information as $singleInformation) {
                switch ($singleInformation->name) {
                    case 'Position': {
                            $eStore->setLatitude($singleInformation->lat)
                                    ->setLongitude($singleInformation->lon);
                            break;
                        }

                    case 'ID': {
                            $eStore->setStoreNumber($singleInformation->content);
                            break;
                        }

                    case 'Öffnungszeiten': {
                            $eStore->setStoreHoursNormalized($singleInformation->content);
                            break;
                        }
                }
            }


            foreach ($singleJStore->address as $singleInformation) {
                switch ($singleInformation->name) {
                    case 'Strasse': {
                            $eStore->setStreetAndStreetNumber($singleInformation->content, 'CH');
                            break;
                        }

                    case 'PLZ': {
                            $eStore->setZipcode($singleInformation->content);
                            break;
                        }

                    case 'Ort': {
                            $eStore->setCity($singleInformation->content);
                            break;
                        }

                    case 'Telefon': {
                            $eStore->setPhoneNormalized($singleInformation->content);
                            break;
                        }

                    case 'E-Mail': {
                            $eStore->setEmail($singleInformation->content);
                            break;
                        }

                    case 'Fax': {
                            $eStore->setFaxNormalized($singleInformation->content);
                            break;
                        }
                }
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
