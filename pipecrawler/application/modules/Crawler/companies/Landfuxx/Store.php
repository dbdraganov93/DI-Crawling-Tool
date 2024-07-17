<?php

/* 
 * Store Crawler für Landfuxx (ID: 29129)
 */

class Crawler_Company_Landfuxx_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.landfuxx.de/';
        $searchUrl = $baseUrl . 'media/system/js/marktfinder.js';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page(true);
$sEncoding = new Marktjagd_Service_Text_Encoding();


        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $pattern = '#var markers = \[(.+?)\];#s';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get marker variable: ' . $searchUrl);
        }

        $pattern = '#\[\'([^\']+)\',\s*([^\]]+)\]#s';
        if (!preg_match_all($pattern, $match[1], $sMatches)) {
            throw new Exception('unable to get stores: ' . $searchUrl);
        }

        foreach ($sMatches[0] as $key => $value) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setDistribution($sMatches[1][$key]);

            $storeText = trim($sMatches[2][$key]);
            $storeText = str_replace("\'", "'", $storeText);

            // subtitle
            $pattern = '#^\'(.+?)\',\s*#s';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setSubtitle(trim($match[1]));
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // geokoordinates
            $pattern = '#^([0-9]{1,2}\.[0-9]+),\s*([0-9]{1,2}\.[0-9]+),\s*#';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setLatitude($match[1]);
                $eStore->setLongitude($match[2]);
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // address
            $pattern = '#^\'([^<]+)<br[^>]*>\s*([0-9]{4,5})\s+([^<]+)<br[^>]*>#';
            if (!preg_match($pattern, $storeText, $match)) {
                $this->_logger->err('unable to get address: ' . $storeText);
            }

            $eStore->setStreetAndStreetNumber(trim($match[1]));
            $eStore->setZipcode($match[2]);
            $eStore->setCity(trim($match[3]));
            $storeText = trim(str_replace($match[0], '', $storeText));

            if (4 == strlen($eStore->getZipcode())) {
                $eStore->setZipcode('0' . $eStore->getZipcode());
            }

            // create number
            $eStore->setStoreNumber($eStore->getZipcode() . '-' .
                strtolower(substr(
                    str_replace(' ', '', $eStore->getCity()), 0, 5
                )) . '-' .
                strtolower(substr(
                    str_replace(' ', '', $eStore->getStreet()), 0, 5
                )));

            // phone
            $pattern = '#^Tel\.:([^<]+)<br[^>]*>#';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setPhoneNormalized($match[1]);
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // fax
            $pattern = '#^Fax:([^<]+)<br[^>]*>#';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setFaxNormalized($match[1]);
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // contact person
            $pattern = '#^(Ansprechpartner:([^<]+))<br[^>]*>#';
            if (preg_match($pattern, $storeText, $match)) {
                if ('' != trim($match[2])) {
                    $eStore->setText($match[1]);
                }
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // website
            $pattern = '#^Web: <a [^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[^<]+</a>\s*<br[^>]*>#';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setWebsite(trim($match[1]));
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // E-Mail
            $pattern = '#^E-Mail: <a[^>]*>([^<]+)</a>\s*(<br[^>]*>)?#';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setEmail(trim($match[1]));
                $storeText = trim(str_replace($match[0], '', $storeText));
            }

            // store hours
            $pattern = '#^<br[^>]*>\s*<strong>Öffnungszeiten:</strong>(.*?)\'$#';
            if (preg_match($pattern, $storeText, $match)) {
                $eStore->setStoreHoursNormalized($match[0]);
            }

            $cStores->addElement($eStore);
        } // Ende Standorte
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}