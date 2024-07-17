<?php

/**
 * crawler für Margrafen (ID: 28827)
 *
 * Class Crawler_Company_Markgrafen_Store
 */
class Crawler_Company_Markgrafen_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {        
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
          
        $baseUrl = 'http://www.markgrafen.com/';
        $storeListUrl = $baseUrl . 'v2/index.php?option=com_filialen&Itemid=30&task=&filialen_land=0&filialen_plz=&'
            . 'filialen_ort=&catid=0&limit=1000&limitstart=0';

        $sPage->open($storeListUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a href="([^"]+)"[^>]*>\s*<img [^>]*alt="Details"[^>]*>\s*</a>#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception('unable to get stores: ' . $storeListUrl);
        }

        foreach ($sMatches[1] as $s => $storeUrl) {
            $eStore = new Marktjagd_Entity_Api_Store();

            // Nummer aus URL
            $pattern = '#filialen_id=([0-9]+)#';
            if (preg_match($pattern, $storeUrl, $match)) {
                $eStore->setStoreNumber($match[1]);
            }

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            // Straße und Nummer
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*Strasse:\s*</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*<strong[^>]*>([^<]*)</strong>\s*</td>#';

            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('unable to get street of address: ' . $storeUrl);
                continue;
            }

            $eStore->setStreetAndStreetNumber(trim($match[1]));

            // Postleiztahl und Ort
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*PLZ/Ort:</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*<strong[^>]*>\s*([0-9]{5})\s*/([^<]+)</strong>\s*</td>#';
            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('unable to get zipcode and city of address: ' . $storeUrl);
                continue;
            }

            $eStore->setZipcode($match[1]);
            $eStore->setCity(trim($match[2]));

            // Telefon
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*Telefon[^<]*</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*<strong[^>]*>([^<]+)</strong>\s*</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            // Telefax
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*Fax[^<]*</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*<strong[^>]*>([^<]+)</strong>\s*</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setFaxNormalized($match[1]);
            }

            // E-Mail-Adresse
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*E-Mail[^<]*</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*<strong[^>]*>([^<]+)</strong>\s*</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setEmail(trim($match[1]));
            }

            // Webseite
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*Homepage[^<]*</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*<strong[^>]*>([^<]+)</strong>\s*</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setWebsite(trim($match[1]));
            }


            // Öfffnungszeiten
            $pattern = '#<td[^>]*>\s*<div[^>]*>\s*Öffnungszeiten[^<]*</div>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>([^<]+)</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}