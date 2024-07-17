<?php

/*
 * Store Crawler für Dolmar (ID: 68897)
 */

class Crawler_Company_Dolmar_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sUrl = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://www.dolmar.de/';
        $searchUrl = $baseUrl . 'cgi/runtime/engine/index.pl?action=dealer_search' .
            '&edition_country=Deutschland&language=de-de' .
            '&m1=1&max_addresses=20&search_str=%20' .
            '&umkreis_point=%28'
            . $sUrl::$_PLACEHOLDER_LAT
            . '%2C%20'
            . $sUrl::$_PLACEHOLDER_LON
            . '%29';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sText = new Marktjagd_Service_Text_TextFormat();
        $aUrls = $sUrl->generateUrl($searchUrl, $sUrl::$_TYPE_COORDS, 0.5);

        foreach ($aUrls as $url) {
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            // find all stores
            $pattern = '#<tr[^>]*>\s*' .
                '<td [^>]*colspan="2"[^>]*>(.+?)</td>\s*' .
                '<td [^>]*colspan="3"[^>]*>.+?</td>\s*' .
                '</tr>\s*' .
                '<tr[^>]*>\s*' .
                '<td[^>]*>([^<]+)</td>\s*' .
                '<td[^>]*>([^<]+)</td>\s*' .
                '<td[^>]*>.+?</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>.+?</td>\s*' .
                '</tr>\s*' .
                '<tr[^>]*>\s*' .
                '<td[^>]*>([^<]+)</td>\s*' .
                '<td[^>]*>([^<]+)</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '<td[^>]*>\s*</td>\s*' .
                '</tr>\s*' .
                '<tr[^>]*>\s*' .
                '<td[^>]*>(.+?)</td>\s*' .
                '</tr>#';
            if (!preg_match_all($pattern, $page, $sMatches)) {
                $this->_logger->warn('unable to get any store: ' . $url);
            }

            // define new store from result
            foreach ($sMatches[0] as $key => $value) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setSubtitle('DOLMAR');
                $eStore->setStreetAndStreetNumber(trim($sMatches[2][$key]));
                $eStore->setPhoneNormalized($sMatches[3][$key]);
                $eStore->setZipcodeAndCity($sText->uncapitalize(trim($sMatches[4][$key])));
                $eStore->setFaxNormalized($sMatches[5][$key]);

                // email and homepgae from text
                $text = trim($sMatches[6][$key]);

                // email
                $pattern = '#<a [^>]*href="mailto:([^"]+)"[^>]*>#';
                if (preg_match($pattern, $text, $match)) {
                    $eStore->setEmail($match[1]);
                }

                // website
                $pattern = '#<a [^>]*href="(https?://[^"]+)"[^>]*>#';
                if (preg_match($pattern, $text, $match)) {
                    $eStore->setWebsite($match[1]);

                    // check website
                    $pattern = '#^https?://([-a-z0-9äöü]+\.)*[-a-z0-9äöü]+\.[a-z]{2,4}$#i';
                    if ($eStore->getWebsite() && !preg_match($pattern, $eStore->getWebsite())) {
                        $eStore->setWebsite(null);
                    }
                }

                $title = $sText->uncapitalize(trim(strip_tags($sMatches[1][$key])));
                // dont get more than one whitespace in the title
                $title = preg_replace('#\s+#', ' ', $title);
                // remove '*' behind the title
                $title = preg_replace('#\s*\*$#', ' ', $title);
                $eStore->setTitle($title);

                if ($eStore->getZipcode() == '16359') {
                    $eStore->setWebsite('http://www.bruchmann-forst-und-gartencenter.de');
                    $eStore->setEmail('info@bruchmann-forst-und-gartencenter.de');
                }

                $cStores->addElement($eStore);
            }
        }
       
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
