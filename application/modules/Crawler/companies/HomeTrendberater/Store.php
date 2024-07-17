<?php

/**
 * Store Crawler für Home Trendberater (ID: 68884)
 */
class Crawler_Company_HomeTrendberater_Store extends Crawler_Generic_Company
{
    public function crawl($companyId) {
        $baseUrl = 'http://www.home-trendberater.de/';
        $searchUrl = $baseUrl . 'HTB-Suche/System/FrameErgebnis.php?plz=';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        $aZip = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

        foreach ($aZip as $zip) {
            $sPage->open($searchUrl . $zip);
            $page = $sPage->getPage()->getResponseBody();

            // alle Standorte finden
            $pattern = '#<tr[^>]*class="td_list"[^>]*>(.+?)</tr>#';
            if (!preg_match_all($pattern, $page, $sMatches)) {
                $this->_logger->err('unable to get stores: ' . $searchUrl . $zip);
            }

            foreach ($sMatches[1] as $k => $storeRow) {
                $eStore = new Marktjagd_Entity_Api_Store();

                // Website
                $pattern = '#<a[^>]*href=\'([^\']+)\'[^>]*>#';
                if (preg_match($pattern, $storeRow, $match)
                    && $match[1] != 'http://'
                ) {
                    $website = str_replace('www..', 'www.', $match[1]);
                    $website = str_replace('www.www.', 'www.', $website);
                    $eStore->setWebsite($website);
                }

                //Subtitle
                $pattern = '#<a[^>]*>([^<]+)<br#';
                if (preg_match($pattern, $storeRow, $match)) {
                    $eStore->setSubtitle($match[1]);
                }

                //Adresse
                $pattern = '#>([^<]+)<br[^>]*>([0-9]{5,6})\s+([^<]+)</a>#';
                if (!preg_match($pattern, $storeRow, $match)) {
                    $this->_logger->err('unable to get address from "' . $storeRow . '": ' . $searchUrl . $zip);
                }

                $eStore->setStreetAndStreetNumber($match[1]);
                $eStore->setZipcode($match[2]);
                $eStore->setCity($match[3]);

                // fix incorrect zipcode
                if ('791189' == $eStore->getZipcode() && 'Bad Krozingen' == $eStore->getCity()) {
                    $eStore->setZipcode('79189');
                }

                //Telefon, Fax
                $pattern = '#</td>\s*<td[^>]*>([^<]+)<br/>([^<]+)</td>#';
                if (preg_match($pattern, $storeRow, $match)) {
                    $eStore->setPhoneNormalized($match[1]);
                    $eStore->setFaxNormalized($match[2]);
                }

                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
