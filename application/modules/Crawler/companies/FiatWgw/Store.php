<?php
/**
 * Store Crawler für Fiat WGW (ID: 72593)
 */

class Crawler_Company_FiatWgw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://dealerlocator.fiat.com/';
        $searchUrl = $baseUrl . 'geocall/RestServlet?jsonp=callback&rad=99&mkt=3103&brand=00&func=finddealerxml&x='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&y=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = json_decode(preg_replace(['#^callback\(#', '#\)$#'], '', $sPage->getPage()->getResponseBody()));

            if (!$jStores->results) {
                continue;
            }
            foreach ($jStores->results as $singleJStore) {
                if (!preg_match('#AUSTRIA#', $singleJStore->NATION)) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $strTimes = '';
                if ($singleJStore->ACTIVITY[0]) {
                    foreach ($singleJStore->ACTIVITY[0] as $singleDay) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        if (property_exists($singleDay, 'AFTERNOON_FROM') && strlen($singleDay->AFTERNOON_FROM)) {
                            $strTimes .= $singleDay->DATEWEEK . ' ' . $singleDay->MORNING_FROM . '-' . $singleDay->MORNING_TO;
                            $strTimes .= ',' . $singleDay->DATEWEEK . ' ' . $singleDay->AFTERNOON_FROM . '-' . $singleDay->AFTERNOON_TO;
                            continue;
                        }
                        $strTimes .= $singleDay->DATEWEEK . ' ' . $singleDay->MORNING_FROM . '-' . $singleDay->AFTERNOON_TO;
                    }
                }

                $eStore->setFaxNormalized($singleJStore->FAX)
                    ->setLatitude($singleJStore->YCOORD)
                    ->setLongitude($singleJStore->XCOORD)
                    ->setEmail($singleJStore->EMAIL)
                    ->setZipcodeAndCity($singleJStore->ZIPCODE . ' ' . $singleJStore->TOWN)
                    ->setPhoneNormalized($singleJStore->TEL_1)
                    ->setWebsite($singleJStore->WEBSITE)
                    ->setStreetAndStreetNumber($singleJStore->ADDRESS)
                    ->setStoreHoursNormalized($strTimes);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}