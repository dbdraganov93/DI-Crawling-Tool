<?php

/*
 * Store Crawler für Segafredo (ID: 71101)
 */

class Crawler_Company_Segafredo_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.segafredofranchising.com/';
        $searchUrl = $baseUrl . 'storelocator/xml/store.xml.php?city=&address=&zipcode=&search=1&country=Germany';
        $imageUrl = $baseUrl . 'storelocator/public/store-locator/normal/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $xmlStores = new SimpleXMLElement($page, LIBXML_NOCDATA);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores as $singleXmlStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $aAddress = preg_split('#\s*,\s*#', $singleXmlStore->address);
            
            for ($i = 0; $i < count($aAddress); $i++) {
                if (preg_match('#[0-9]{5}#', $aAddress[$i])) {
                    $eStore->setZipcode(trim($aAddress[$i]))
                            ->setCity($aAddress[$i + 1])
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[$i - 1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[$i - 1])));
                }
            }
            
            if (preg_match('#[0-9]{5}\s+.+#', $eStore->getZipcode(), $strAddress)) {
                $eStore->setCity($sAddress->extractAddressPart('city', $strAddress[0]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $strAddress[0]));
            }

                foreach ($singleXmlStore->attributes() as $key => $value) {
                    if (preg_match('#type#', $key)) {
                        $eStore->setDistribution(ucwords($value));
                        continue;
                    }
                    if (preg_match('#lat#', $key)) {
                        $eStore->setLatitude((string) $value);
                        continue;
                    }
                    if (preg_match('#lng#', $key)) {
                        $eStore->setLongitude((string) $value);
                    }
                }

                $aImages = array();
                foreach ($singleXmlStore->images->image as $singleImage) {
                    foreach ($singleImage->attributes() as $key => $value) {
                        if (preg_match('#path#', $key) && count($aImages) < 3) {
                            $aImages[] = $imageUrl . $value;
                        }
                    }
                }

                $eStore->setImage(implode(', ', $aImages));

                $cStores->addElement($eStore);
            }

            $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
            $fileName = $sCsv->generateCsvByCollection($cStores);

            return $this->_response->generateResponseByFileName($fileName);
        }
    }
    