<?php

/*
 * Store Crawler für Autohaus Gottfried Schultz (ID: 72044)
 */

class Crawler_Company_GottfriedSchultz_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.gottfried-schultz.de';
        $url = $baseUrl . '/standorte/uebersicht/';

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*branches\s*=\s*(\[[^;]+?);#i';
        if (!preg_match($pattern, $page, $overview)) {
            throw new Exception($companyId . ': unable to find any stores.');
        }

        $pattern = '#(\[[^\]]+?])#i';
        if (!preg_match_all($pattern, $overview[1], $branches)) {
            throw new Exception($companyId . ': unable to find any store branches.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($branches[1] as $branch) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<address>(.+?)<\/address>#i';
            if (!preg_match($pattern, $branch, $address)) {
                $this->_logger->err('unable to find store address.');
                continue;
            }

            $pattern = '#<strong>(.+?)<\/strong>#i';
            if (!preg_match($pattern, $address[1], $title)) {
                $this->_logger->err('unable to find store titel.');
                continue;
            } else {
                $eStore->setTitle(trim($title[1]));
            }

            $tmp = preg_replace('#<strong>.+?<\/strong>#', '', $address[1]);
            $tmp = preg_replace('#\+#', '', $tmp);
            $tmp = preg_split('#<br>#', $tmp);
            $eStore->setStreetAndStreetNumber(trim(preg_replace(['#\s{2}#', "#'#"], '', $tmp[1])));
            $eStore->setZipcodeAndCity(trim(preg_replace(['#\s{2}#', "#'#"], '', $tmp[2])));

            $pattern = '#(\d{1,2}\.\d{6}),\s(\d{1,2}\.\d{6})#';
            if (preg_match($pattern, $branch, $longLat)) {
                $eStore->setLatitude($longLat[1]);
                $eStore->setLongitude($longLat[2]);
            }

            if (preg_match("#href=\"(/standorte/[^\"]+?)\"#", $branch, $url)) {
                $eStore->setWebsite($baseUrl . $url[1]);
                $contactDetails = $sPage->getDomElsFromUrlByClass($baseUrl . $url[1], 'branch__contact-details fa-ul');
                if ($contactDetails == NULL) {
                    $this->_logger->err('unable to find store contact details: ' . $baseUrl . $url[1]);
                } else {
                    $contactDetails = $contactDetails[0]->C14N();
                    $pattern = '#fa-phone\sfa-li.+?href=\"tel:([^\"]+?)\"#i';
                    if (!preg_match($pattern, $contactDetails, $phoneMatch)) {
                        $this->_logger->err($companyId . ': unable to find phone number for store: ' . $url[1]);
                    } else {
                        $eStore->setPhoneNormalized($phoneMatch[1]);
                    }
                }

                $openingHours = $sPage->getDomElsFromUrlByClass($baseUrl . $url[1], 'table-verkauf');
                if ($openingHours == NULL) {
                    $openingHours = $sPage->getDomElsFromUrlByClass($baseUrl . $url[1], 'table-werkstatt-service');
                }
                if ($openingHours == NULL) {
                    $this->_logger->err('unable to find store opening hours: ' . $baseUrl . $url[1]);
                } else {
                    $openingHours = $openingHours[0]->C14N();
                    $eStore->setStoreHoursNormalized(preg_replace(['#\s{2}#', '#Verkauf#', '%&#xD;%'], ' ', strip_tags($openingHours)));
                }
            } else {
                $this->_logger->err('unable to find store details page: ' . $branch);
            }
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
