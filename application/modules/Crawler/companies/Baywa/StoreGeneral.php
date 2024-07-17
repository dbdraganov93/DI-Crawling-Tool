<?php

/*
 * Store Crawler für BayWa (ID: 24947)
 */

class Crawler_Company_Baywa_StoreGeneral extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.baywa.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $northEastLat	= 56.000;	// 55.081500
        $northEastLng	= 16.000;	// 15.0418321
        $southWestLat	= 47.000;	// 47.2701270
        $southWestLng	= 5.000;	// 5.8663566
        $regionPost = array(
            'eID'					=> 'essolLocatorAjaxDispatcher',
            'extensionName'			=> 'EssolLocator',
            'pluginName'			=> 'pi2',
            'controllerName'		=> 'GmEntity',
            'action'				=> 'rectangleToEntitiesAction',
            'arguments[task]'		=> 'rectangleToEntities',
            'arguments[id]'			=> 'USID1317807380472',
            'arguments[input][rectangle][ne][geo_data][latitude]'	=> $northEastLat,
            'arguments[input][rectangle][ne][geo_data][longitude]'	=> $northEastLng,
            'arguments[input][rectangle][sw][geo_data][latitude]'	=> $southWestLat,
            'arguments[input][rectangle][sw][geo_data][longitude]'	=> $southWestLng,
        );
        $storePost = array(
            'eID'									=> 'essolLocatorAjaxDispatcher',
            'extensionName'							=> 'EssolLocator',
            'pluginName'							=> 'pi2',
            'controllerName'						=> 'GmEntity',
            'action'								=> 'geoToEntitiesAction',
            'arguments[task]'						=> 'geoToEntities',
            'arguments[input][geo_data][latitude]'	=> false,
            'arguments[input][geo_data][longitude]'	=> false,
            'arguments[id]'							=> 'USID1320247737891',
        );
        $typeLabel = 'An diesem Standort finden sie folgende Märkte: ';
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

        $sPage->open($baseUrl, $regionPost);
        $page = $sPage->getPage()->getResponseBody();

        // Standorte finden
        $pattern = '#"gm_entity_short":\{' .
            '"geo_data":\{"latitude":"([^"]+)","longitude":"([^"]+)"\},' .
            '"address_data":\{([^\}]+)\},' .
            '"feature_data_short":\{([^\}]+)\}' .
            '\}#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception($companyId .': unable to get stores');
        }

        foreach ($sMatches[0] as $k => $value) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setLatitude($sMatches[1][$k]);
            $eStore->setLongitude($sMatches[2][$k]);

            $address = trim($sMatches[3][$k]);

            // Adresse verarbeiten
            $pattern = '#"([^"]+)":"([^"]*)"#';
            if (!preg_match_all($pattern, $address, $matches)) {
                $this->_logger->err('unable to get address-values from text "' . $address . '"');
                continue;
            }
            for ($i = 0; $i < count($matches[0]); $i++) {
                $key = trim($matches[1][$i]);
                $value = trim($this->_encodeJsonString($matches[2][$i]));
                switch ($key) {
                    case 'zip':
                        if (!preg_match('#^([0-9]{5})#', $value, $match)) {
                            $this->_logger->err('invalid zipcode "' . $value . '"');
                            continue;
                        }
                        $eStore->setZipcode($match[1]);
                        break;
                    case 'city':
                        $eStore->setCity($value);
                        break;
                    case 'street':
                        $eStore->setStreetAndStreetNumber($value);
                        break;
                    case 'title':
                        $eStore->setSubtitle($value);
                        break;
                    case 'country':
                    case 'ids':
                        break;
                    default:
                        $this->_logger->err('unknown address-param "' . $key . '" with value "' . $value . '"');
                        break;
                }
            }

            // Workaround füer Scheßlitz
            if ('Schesslitz' == $eStore->getCity()) {
                $eStore->setCity('Scheßlitz');
            }

            // Detailseite laden
            $detailPost = $storePost;
            $detailPost['arguments[input][geo_data][latitude]'] = $eStore->getLatitude();
            $detailPost['arguments[input][geo_data][longitude]'] = $eStore->getLongitude();

            $sPage->open($baseUrl, $detailPost);

            $detailPage = $this->_encodeJsonString($sPage->getPage()->getResponseBody());

            // Vertriebsbereiche
            $pattern = '#"facility_type":"([^"]+)"#';
            if (preg_match_all($pattern, $detailPage, $matches)) {
                $eStore->setDistribution(implode(', ', $matches[1]));
                $eStore->setText($typeLabel . $eStore->getDistribution());
            }

            // Telefon
            $pattern = '#"phone":"([^"]*)","fax":"([^"]*)","facility_type":"([^"]+)"#';
            if (preg_match_all($pattern, $detailPage, $matches)) {
                $eStore->setPhone(preg_replace($telPattern, $telReplacement, $matches[1][0]));
                if (!preg_match('#^0#', $eStore->getPhone())) {
                    $eStore->setPhone(null);
                }

                if (1 < count($matches[0])) {
                    for ($t = 0; $t < count($matches[0]); $t++) {
                        $phone = trim($matches[1][$t]);
                        $fax = trim($matches[2][$t]);
                        if ($phone || $fax) {
                            $text = $eStore->getText();
                            $distribution = trim($matches[3][$t]);
                            if (!preg_match('#baywa#i', $distribution)) {
                                $distribution = 'BayWa ' . $distribution;
                            }
                            $text .= ('' != $text ? '<br /><br />' : '') . $distribution . '<br />';
                            if ($phone) {
                                $text .= 'Tel.: ' . $phone;
                            }
                            if ($phone && $fax) {
                                $text .= '<br />';
                            }
                            if ($fax) {
                                $text .= 'Fax.: ' . $fax;
                            }

                            $eStore->setText($text);
                        }
                    }
                }
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

    protected function _encodeJsonString($str)
    {
        $replacements = array(
            '\/' => '/',
            '\"' => '"',
            '\u00c4' => 'Ä',
            '\u00e4' => 'ä',
            '\u00d6' => 'Ö',
            '\u00f6' => 'ö',
            '\u00dc' => 'Ü',
            '\u00fc' => 'ü',
            '\u00df' => 'ß',
            '\u00b0' => '°',
            '\u00e9' => 'é',
            '\u00bf' => '¿',
            '\u00a0' => ' ',
            '\u0026' => '&',
            '\u2013' => '-',
            '\u00ae' => '®',
            '\x3c' => '<',
            '\x3e' => '>');

        foreach ($replacements as $s => $r) {
            $str = str_ireplace($s, $r, $str);
        }

        return $str;
    }
}
