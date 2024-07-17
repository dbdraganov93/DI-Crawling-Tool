<?php

/**
 * Klasse zum Generieren von URLs
 *
 * Class Marktjagd_Service_Generator_Url
 */
class Marktjagd_Service_Generator_Url
{

    public static $_PLACEHOLDER_LAT = '[[LAT]]';
    public static $_PLACEHOLDER_LON = '[[LON]]';
    public static $_PLACEHOLDER_LAT_STEP = '[[LAT_STEP]]';
    public static $_PLACEHOLDER_LON_STEP = '[[LON_STEP]]';
    public static $_PLACEHOLDER_ZIP = '[[ZIP]]';

    public static $_TYPE_ZIP = 'zip';
    public static $_TYPE_COORDS = 'coords';
    public static $_TYPE_RECT = 'rect';

    /**
     * Erstellt Array mit Geodaten-Urls
     *
     * @param string $sUrl URL, welche erzeugt werden soll
     * @param string $type Typ anhand die URLs erzeugt werden sollen, entweder Koordinaten oder PLZ
     * @param bool|float $geoSteps Rastergröße
     * @param string $country Landeskürzel
     * @return array $aUrl Array mit den erzeugten Urls
     * @throws Exception
     */
    public function generateUrl($sUrl, $type = 'coords', $geoSteps = false, $country = 'DE')
    {
        $aUrl = array();

        $country = strtoupper($country);

        switch ($country) {

            case "BG":
            {
                $southLat = 41.24;
                $northLat = 44.21;
                $westLong = 28.60;
                $eastLong = 29.60;
                break;
            }

            case "RO":
            {
                $southLat = 43.62;
                $northLat = 48.25;
                $westLong = 20.26;
                $eastLong = 29.60;
                break;
            }

            case "NE":
            {
                $southLat = 50.75;
                $northLat = 53.46;
                $westLong = 3.38;
                $eastLong = 6.83;
                break;
            }

            case "IT":
            {
                $southLat = 36.64;
                $northLat = 47.06;
                $westLong = 6.644;
                $eastLong = 13.71;
                break;
            }

            case "CRO":
            {
                $southLat = 42.39;
                $northLat = 46.54;
                $westLong = 16.84;
                $eastLong = 22.55;
                break;
            }

            case "SLK":
            {
                $southLat = 47.73;
                $northLat = 49.59;
                $westLong = 16.84;
                $eastLong = 22.55;
                break;
            }

            case "SLO":
            {
                $southLat = 45.42;
                $northLat = 46.63;
                $westLong = 13.59;
                $eastLong = 16.57;
                break;
            }

            case "HU":
            {
                $southLat = 45.75;
                $northLat = 48.53;
                $westLong = 16.18;
                $eastLong = 22.84;
                break;
            }

            case "HR":
            {
                $southLat = 43.60;
                $northLat = 46.30;
                $westLong = 14.20;
                $eastLong = 20.10;
                break;
            }

            case "PL":
            {
                $southLat = 49.056;
                $northLat = 54.8244;
                $westLong = 14.1248;
                $eastLong = 24.1177;
                break;
            }


            case 'DE':
            {
                $southLat = 47.200;     // 47.2701270
                $northLat = 55.200;     // 55.081500
                $westLong = 5.800;      // 5.8663566
                $eastLong = 15.200;     // 15.0418321
                break;
            }
            case 'CH':
            {
                $southLat = 45.49;
                $northLat = 47.48;
                $westLong = 5.57;
                $eastLong = 10.29;
                break;
            }
            case  'AT':
            {
                $southLat = 46.373;
                $northLat = 49.021;
                $westLong = 9.530;
                $eastLong = 17.161;
                break;
            }
            case  'FR':
            {
                $southLat = 42.3357;
                $northLat = 51.0878;
                $westLong = -5.1389;
                $eastLong = 8.2301;
                break;
            }
            case  'GRC':
            {
                $southLat = 34.8;
                $northLat = 41.7;
                $westLong = 19.4;
                $eastLong = 28.3;
                break;
            }
            case  'DNK':
            {
                $southLat = 54.5;
                $northLat = 58;
                $westLong = 8;
                $eastLong = 15;
                break;
            }
            case  'LTU':
            {
                $southLat = 53.9;
                $northLat = 56.4;
                $westLong = 20.7;
                $eastLong = 26.8;
                break;
            }
            case  'LUX':
            {
                $southLat = 49.4;
                $northLat = 50.2;
                $westLong = 5.7;
                $eastLong = 6.5;
                break;
            }
            default:
            {
                throw new Exception('not supported country code: ' . $country);
            }
        }
        if ($type == 'coords'
            || $type == 'geo'
            || $type == 'coordinates'
        ) {
            if (!$geoSteps) {
                $geoSteps = 0.2;
            }

            for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
                for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                    $aUrl[] = preg_replace(array(
                        '#\[\[LAT\]\]#',
                        '#\[\[LON\]\]#'
                    ), array(
                        $lat,
                        $long
                    ), $sUrl);
                }
            }
        }

        if ($type == 'rect') {
            if (!$geoSteps) {
                $geoSteps = 0.2;
            }

            for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
                for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                    $aUrl[] = preg_replace(array(
                        '#\[\[LAT\]\]#',
                        '#\[\[LON\]\]#',
                        '#\[\[LAT\_STEP\]\]#',
                        '#\[\[LON\_STEP\]\]#'
                    ), array(
                        $lat,
                        $long,
                        ($lat + $geoSteps),
                        ($long + $geoSteps)
                    ), $sUrl);
                }
            }
        }

        if ($type == 'plz'
            || $type == 'zip'
            || $type == 'zipcode'
        ) {
            if (!$geoSteps) {
                $geoSteps = 5;
            }
            $sGeo = new Marktjagd_Database_Service_GeoRegion();
            $aZip = $sGeo->findZipCodesByNetSize($geoSteps, FALSE, $country);
            foreach ($aZip as $zip) {
                $aUrl[] = preg_replace('#\[\[ZIP\]\]#', $zip, $sUrl);
            }
        }

        return $aUrl;
    }

}
