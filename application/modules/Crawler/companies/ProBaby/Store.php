<?php

/**
 * Storecrawler für Pro Baby (ID: 24948)
 */
class Crawler_Company_ProBaby_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $baseUrl = 'http://www.probaby.de/';
        $xmlUrl = $baseUrl . 'haendlersuche/suche.php?country=de';

        $telPattern = array(
            '#[^0-9]#',
            '#^0049#',
            '#^49#',
            '#^00#',
        );
        $telReplacement = array(
            '',
            '0',
            '0',
            '0',
        );

        $sPage->open($xmlUrl);
        $page = $sPage->getPage()->getResponseBody();



        if (!preg_match('#GDownloadUrl\(\"([^\"]+)\"#', $page, $sMatches)) {
            throw new Exception('unable to get xml file: ' . $xmlUrl);
        }

        $subPage = $sMatches[1];
        $sPage->open($subPage);
        $page = $sPage->getPage()->getResponseBody();

        // Alle Marker finden
        $pattern = '#<marker\s+([^<]+)>#i';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception('unable to get marker: ' . $subPage);
        }

        foreach ($sMatches[0] as $key => $value) {
            $attributes = trim($sMatches[1][$key]);

            // attribute finden
            $pattern = '#([a-z]+)="([^"]*)"#';
            if (!preg_match_all($pattern, $attributes, $aMatches)) {
                $this->_logger->err('unable to get attributes from marker "' . $sMatches[0][$key] . '": ' . $subPage);
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            for ($a = 0; $a < count($aMatches[0]); $a++) {
                $key = $aMatches[1][$a];
                $value = trim($aMatches[2][$a]);

                switch ($key) {
                    case 'name':
                        $eStore->setSubtitle($value);
                        break;
                    case 'strasse':
                        $eStore->setStreetAndStreetNumber($value);
                        break;
                    case 'plz':
                        $eStore->setZipcode($value);
                        break;
                    case 'ort':
                        $eStore->setCity($value);
                        break;
                    case 'id':
                        $eStore->setStoreNumber($value);
                        break;
                    case 'phone':
                        $eStore->setPhone(preg_replace($telPattern, $telReplacement, $value));
                        break;
                    case 'fax':
                        $eStore->setFax(preg_replace($telPattern, $telReplacement, $value));
                        break;
                    case 'email':
                        $eStore->setEmail($value);
                        break;
                    case 'web':
                        $eStore->setWebsite($value?'http://' . $value:'');
                        break;
                    case 'lat':
                        $eStore->setLatitude($value);
                        break;
                    case 'lng':
                        $eStore->setLongitude($value);
                        break;
                    default:
                        $this->_logger->err('unknwon attribute "' . $key . '" with value "' . $value . '": ' . $xmlUrl);
                        continue;
                }
            }

            if ($eStore->getStoreNumber()) {
                $sPage->open('http://www.probaby.de/haendlersuche/detail.php?id=' . $eStore->getStoreNumber());
                $detailPage = $sPage->getPage()->getResponseBody();
                $pattern = '#ffnungszeiten</strong>(.+?)</table>#';

                if (preg_match($pattern, $detailPage, $match)) {
                    $eStore->setStoreHoursNormalized($match[1]);
                }
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
