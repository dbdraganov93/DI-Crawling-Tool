<?php

/**
 * Beinhaltet Funktionalitäten zum Bearbeiten / Umwandeln / Vergleichen von Adressdaten
 */
class Marktjagd_Service_Text_Address
{

    public static $EXTRACT_STREET = 'street';
    public static $EXTRACT_STREET_NR = 'streetnumber';
    public static $EXTRACT_ZIP = 'zip';
    public static $EXTRACT_CITY = 'city';

    protected $_logger;

    public function __construct()
    {
        $this->_logger = Zend_Registry::get('logger');
    }

    /**
     * Extrahiert einen Teil der Adresse aus einem String
     *
     * @param string $type Art des zu extrahierenden Adressteils
     * @param string $text Teil der Adresse
     * @return string
     */
    public function extractAddressPart($type, $text, $localCode = 'DE')
    {
        $localCode = strtoupper(substr($localCode, 0, 2));
        $retValue = '';
        $text = html_entity_decode($text);
        if (!preg_match('#FR#', $localCode)) {
            $text = mb_convert_encoding($text, mb_detect_encoding($text));
        }

        if (substr(strtolower($type), 0, 6) == 'street') {
            $streetNumber = '';

            // Autobahn/Bundesstrasse (A4, B170, An der A2)
            if (preg_match('#^([A-Za-z])\s*([0-9]+)#', trim($text), $match) || preg_match('#^(.+\s+[A-Za-z])\s*([0-9]+)#', trim($text), $match)) {
                $street = $match[1] . $match[2];
                // Strasse des 17. Juni 3
            } elseif (preg_match('#(.+?[0-9]+[^/\(\)\,\-]*[A-Za-z]{3,}[^/\(\)\,\-]*)([0-9]+.*)$#', $text, $match)) {
                $street = $match[1];
                $streetNumber = $this->normalizeStreetNumber(trim($match[2]));
                // Strasse des 17. Juni (ohne Hausnummer)
            } elseif (preg_match('#(.+?[0-9]+[^/\(\)\,\-]*[A-Za-z]{3,}[^/\(\)\,\-]*)$#', $text, $match)) {
                $street = $match[1];
                // "normale" Angabe
            } elseif (preg_match('#([^0-9]+)([0-9]+.*)#', $text, $match)) {
                $street = $this->normalizeStreet(trim($match[1]), $localCode);
                $streetNumber = $this->normalizeStreetNumber(trim($match[2]));
                // Strasse ohne Hausnummer
            } else {
                $street = trim($text);
            }

            // Hausnummer zurück
            if (strtolower($type) == 'streetnumber' || 'streetnr' || 'street_number') {
                $retValue = $this->normalizeStreetNumber($streetNumber);
            }

            // Straße zurück
            if (strtolower($type) == 'street') {
                $retValue = $this->normalizeStreet($street, $localCode);
            }
        }

        // PLZ
        if (strtolower($type) == 'zip' || strtolower($type) == 'zipcode'
        ) {
            $retValue = preg_replace('#^D-#', '', $text);
            if (preg_match('#([0-9]+)\s*(.*)#', $retValue, $match)) {
                $retValue = trim($match[1]);
            } else {
                $retValue = '';
            }
        }

        // Stadt
        if (strtolower($type) == 'city') {
            $retValue = preg_replace('#^D-#', '', $text);
            if (preg_match('#([0-9]+)\s*(.*)#', $retValue, $match)) {
                $retValue = trim($match[2]);
            } else {
                $retValue = trim($text);
            }

            $retValue = $this->normalizeCity($retValue);
        }

        return $retValue;
    }

    /**
     * Normalisiert die Hausnummer
     *
     * @param string $streetNumber
     * @return string
     */
    public function normalizeStreetNumber($streetNumber)
    {
        $streetNumberPattern = array(
            '#\s*[-|–]\s*#i',
            '#\s*\+\s*#',
            '#\s*\/\s*#',
            '#\s*(\/|\()\s*[A-Za-z]{2,}.*?$#',
            '#(\s+[-|–]\s+)+#i',
        );

        $streetNumberReplacement = array(
            ' - ',
            ' + ',
            ' / ',
            '',
            ' - '
        );

        return strtolower(preg_replace($streetNumberPattern, $streetNumberReplacement, $streetNumber));
    }

    /**
     * Normalisiert Straßennamen
     * aus Abkürzungen und "ss" wird "straße"
     * entfernt komplette Großschreibung
     *
     * @param string $street Straßenname
     * @param string $localCode Ländercode
     * @return string
     */
    public function normalizeStreet($street, $localCode = 'DE')
    {
        $localCode = strtoupper(substr($localCode, 0, 2));
        $aMatches = array('DE' => array('pattern' => '#(s|S)tr(\.|asse| )#',
            'replacement' => '$1traße'),
            'CH' => array('pattern' => '#(s|S)tr(\.|aße| )#',
                'replacement' => '$1trasse')
        );

        $sText = new Marktjagd_Service_Text_TextFormat();
        $street = $sText->uncapitalize(html_entity_decode($street));

        if (array_key_exists($localCode, $aMatches)) {
            $street = trim(preg_replace($aMatches[$localCode]['pattern'], $aMatches[$localCode]['replacement'], $street . ' '));
        }

        return $street;
    }

    /**
     * Normalisiert die Stadt
     * entfernt komplette Großschreibung
     *
     * @param $city
     * @return string
     */
    public function normalizeCity($city)
    {
        $sText = new Marktjagd_Service_Text_TextFormat();
        $city = $sText->uncapitalize($city);

        return $city;
    }

    public function normalizeEmail($mail)
    {
        $mail = strtolower(
            trim(
                preg_replace(
                    array('#(\(|\<)at(\)|\>)#', '#(.*?)\s*([a-zäöü\-\.\&]+?\@[^\s]+?\.[a-z]+)#i', '#(\(|\<)dot(\)|\>)#'), array('@', '$2', '.'), $mail
                )
            )
        );
        return strip_tags($mail);
    }

    /**
     * Normalisiert die Telefonnummer
     *
     * @param $phone
     * @return string
     */
    public function normalizePhoneNumber($phone)
    {
        $telPattern = array(
            '#[^0-9]#',
            '#^004\d#',
            '#^4\d#',
            '#^00#',
        );
        $telReplacement = array(
            '',
            '0',
            '0',
            '0',
        );

        return preg_replace($telPattern, $telReplacement, $phone);
    }

    /**
     * Prüft, ob Geokoordinate valide ist
     *
     * @param string $coordType
     * @param string $coordValue
     * @return string
     */
    public function validateGeoCoords($coordType, $coordValue)
    {
        switch ($coordType) {
            case 'latitude':
            case 'lat':
            {
                if ((float)$coordValue < 45 || (float)$coordValue > 56) {
                    $coordValue = '';
                }
                break;
            }
            case 'longitude':
            case 'lng':
            {
                if ((float)$coordValue < 5 || (float)$coordValue > 17) {
                    $coordValue = '';
                }
            }
        }
        return $coordValue;
    }

    /**
     * Erzeugt anhand gegebenem Abstand ein PLZ-Gitter
     *
     * @param string $netSize Maschengröße
     * @param string $localCode Länderkürzel
     * @param bool $geoLocation
     *
     * @return array
     */
    public function getRegionGrid($netSize, $localCode = 'DE', $geoLocation = FALSE)
    {
        $localCode = strtoupper(substr($localCode, 0, 2));
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();
        switch ($localCode) {
            case 'DE':
            {
                $startLng = 6.0;
                $endLng = 15;
                $startLat = 47.5;
                $endLat = 54.8;
                break;
            }
            case 'CH':
            {
                $startLng = 5.57;
                $endLng = 10.29;
                $startLat = 45.49;
                $endLat = 47.48;
                break;
            }
            case  'FR':
            {
                $startLng = -5.1389;
                $endLng = 8.2301;
                $startLat = 42.3357;
                $endLat = 51.0878;
                break;
            }
            case 'AT':
            {
                $startLng = 9.530;
                $endLng = 17.161;
                $startLat = 46.373;
                $endLat = 49.021;
                break;
            }
            default:
            {
                throw new Exception('invald local code.');
            }
        }
        $radius = 6378.137;
        $aGeoData = array();
        $retArr = array();
        $aGeoRegion = $sDbGeoRegion->findAll($localCode);
        $aZipcodes = array();

        $northSouthDist = ($endLat - $startLat) * (2 * pi() * $radius / 360);
        $diffY = ($netSize / $northSouthDist) * ($endLat - $startLat);
        $lat = $startLat;

        // Erstellung des Geokoordinaten-Feldes
        while ($lat <= $endLat) {
            $eastWestDist = acos(cos(deg2rad($endLng - $startLng)) * pow(cos(deg2rad($lat)), 2) + pow(sin(deg2rad($lat)), 2)) * $radius;
            $diffX = ($netSize / $eastWestDist) * ($endLng - $startLng);

            $lng = $startLng;

            while ($lng <= $endLng) {
                $aGeoData[] = array(
                    'lat' => $lat,
                    'lng' => $lng
                );
                $lng += $diffX;
            }

            $lat += $diffY;
        }

        // Berechnung der PLZ, welche den geringsten Abstand zu den gegebenen Geokoordinaten hat
        foreach ($aGeoData as $singleGeoData) {
            $maxDistance = 5000;
            foreach ($aGeoRegion as $singleGeoRegion) {
                /* @var $singleGeoRegion Marktjagd_Database_Entity_GeoRegion */
                $distance = $this->calculateDistanceFromGeoCoordinates(
                    $singleGeoData['lat'], $singleGeoData['lng'], $singleGeoRegion->getLatitude(), $singleGeoRegion->getLongitude());
                if ($distance < $netSize && $distance < $maxDistance) {
                    $maxDistance = $distance;
                    $aZipcodes[$singleGeoData['lat'] . '|' . $singleGeoData['lng']] = array(
                        'zipcode' => $singleGeoRegion->getZipcode(),
                        'city' => $singleGeoRegion->getCity(),
                        'distance' => $distance);
                }
            }
        }
        foreach ($aZipcodes as $geoCoords => $singleZipcode) {
            if ($geoLocation) {
                $aGeo = preg_split('#\|#', $geoCoords);
                $retArr[$singleZipcode['zipcode']]['zip'] = $singleZipcode['zipcode'];
                $retArr[$singleZipcode['zipcode']]['city'] = $singleZipcode['city'];
                $retArr[$singleZipcode['zipcode']]['lat'] = $aGeo[0];
                $retArr[$singleZipcode['zipcode']]['lng'] = $aGeo[1];
            } else {
                $retArr[$singleZipcode['zipcode']] = $singleZipcode['zipcode'];
            }
        }

        return $retArr;
    }

    /**
     * Entfernungsberechnung zwischen 2 Geokoordinaten nach Thaddeus Vincenty
     *
     * @param float $x1 Breitengrad 1
     * @param float $y1 Längengrad 1
     * @param float $x2 Breitengrad 2
     * @param float $y2 Längengrad 2
     * @return float Entfernung
     */
    public function calculateDistanceFromGeoCoordinates($x1, $y1, $x2, $y2)
    {
        $earthFlattening = 1 / 298.257223563;
        $radius = 6378.137;

        $F = ($x1 + $x2) / 2;
        $G = ($x1 - $x2) / 2;
        $l = ($y1 - $y2) / 2;

        $S = pow(sin(deg2rad($G)), 2) * pow(cos(deg2rad($l)), 2) + pow(cos(deg2rad($F)), 2) * pow(sin(deg2rad($l)), 2);
        $C = pow(cos(deg2rad($G)), 2) * pow(cos(deg2rad($l)), 2) + pow(sin(deg2rad($F)), 2) * pow(sin(deg2rad($l)), 2);

        $w = atan(sqrt($S / $C));

        $D = 2 * $w * $radius;

        $T = sqrt($S * $C) / $w;

        $H1 = (3 * $T - 1) / (2 * $C);
        $H2 = (3 * $T + 1) / (2 * $S);

        $distance = $D * (1 + $earthFlattening * $H1 * pow(sin(deg2rad($F)), 2) * pow(cos(deg2rad($G)), 2) - $earthFlattening * $H2 * pow(cos(deg2rad($F)), 2) * pow(sin(deg2rad($G)), 2));

        return $distance;
    }

    /**
     * @param string $city
     * @param string $street
     * @param string $hnr
     * @return string
     * @throws Exception
     */
    public function getGerZipCode($city, $street, $hnr)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $url = 'https://www.dastelefonbuch.de/Postleitzahlen?' .
            'ci=' . urlencode($city) . '&' .
            'st=' . urlencode($street) . '&' .
            'hn=' . urlencode($hnr);

        $zipCodeRaw = $sPage->getDomElFromUrlByID($url, 'postalcode');
        $zipCode = trim($zipCodeRaw->textContent);
        if (!preg_match('#^\d{5}$#', $zipCode)) {
            $this->_logger->err("$city, $street $hnr: Zipcode matches not with the pattern(^\d{5}$): \"$zipCode\"");
        }
        return $zipCode;
    }

    /**
     * @param string $zipcode
     * @return string
     * @throws Zend_Exception
     */
    public function getGerCityName($zipcode)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $url = 'https://www.dastelefonbuch.de/Postleitzahlen?' .
            'pc=' . urlencode($zipcode);

        $domList = $sPage->getDomElFromUrlByID($url, 'postalcode_table');
        $cityRaw = $sPage->getDomElsFromDomEl($domList, 'arrow', 'class', 'a');
        $cityName = trim($cityRaw[0]->textContent);
        if (!preg_match('#^[A-Z][\w-]+#', $cityName)) {
            $this->_logger->err("$zipcode: City name matches not with the pattern(^[A-Z][\w-]+): \"$cityName\"");
        }
        return $cityName;
    }
}
