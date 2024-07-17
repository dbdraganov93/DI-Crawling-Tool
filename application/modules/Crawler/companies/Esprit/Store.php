<?php

/**
 * Store Crawler für Esprit (ID: 347)
 */
class Crawler_Company_Esprit_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.esprit.com';
        $searchUrl = $baseUrl . '/storefindereshop?country_id=DE&lang=de';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select[^>]*name="city_id"[^>]*>(.+?)</select#s';
        if (!preg_match($pattern, $page, $cityListMatch)) {
            throw new Exception($companyId . ': unable to get city list.');
        }

        $pattern = '#<option[^>]*value="([^"]+?)">#';
        if (!preg_match_all($pattern, $cityListMatch[1], $cityMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($cityMatches[1] as $singleCity) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);

            $sPage->open($searchUrl, ['city_id' => $singleCity]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="entry"[^>]*>(.+?)<br[^>]*class="fix"#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get store list for city ' . $singleCity);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<h4[^>]*>(.+?)<br#';
                if (preg_match($pattern, $singleStore, $subTitleMatch)) {
                    if (!preg_match('#Esprit#', $subTitleMatch[1])) {
                        continue;
                    }
                }

                $pattern = '#<\/h4>(\s*<h4[^>]*>.+?<\/h4>)?\s*(.+?)<(a|div)#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address.');
                    continue;
                }
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[2]);
                for ($i = 0; $i < count($aAddress); $i++) {
                    if (preg_match('#[0-9]{5}\s+[A-Z]+#', $aAddress[$i])) {
                        $eStore->setZipcodeAndCity($aAddress[$i])
                            ->setStreetAndStreetNumber($aAddress[$i - 1]);

                        if (!strlen($sAddress->extractAddressPart('streetnumber', $aAddress[$i - 1]))) {
                            $eStore->setStreetAndStreetNumber($aAddress[$i - 2])
                                ->setSubtitle($aAddress[$i - 1]);
                        }
                    }

                    if (preg_match('#Telefon#i', $aAddress[$i])) {
                        $eStore->setPhone($sAddress->normalizePhoneNumber($aAddress[$i]));
                    }
                    if (preg_match('#Fax#i', $aAddress[$i])) {
                        $eStore->setFax($sAddress->normalizePhoneNumber($aAddress[$i]));
                    }
                }

                $pattern = '#<h4[^>]*>\s*Öffnungszeiten\s*<\/h4>(.+?)<\/div>#s';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                if (preg_match('#<h4[^>]*>Produkte<\/h4>(.+?)<\/div>#', $singleStore, $productsMatch)) {
                    $products = preg_split('#<br[^>]*>#', $productsMatch[1]);
                    $eStore->setSection(trim(implode(', ', $products)));
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
