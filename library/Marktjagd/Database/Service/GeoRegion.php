<?php

/**
 * Service zum Ermitteln von Geodaten aus der Datenbank
 *
 * Class Marktjagd_Database_Service_GeoRegion
 */
class Marktjagd_Database_Service_GeoRegion extends Marktjagd_Database_Service_Abstract
{

    /**
     * Ermittelt anhand der übergebenen Postleitzahl das entsprechende Bundesland
     * @param string $zipCode
     * @return string
     */
    public function findRegionByZipCode($zipCode, $localCode = 'DE')
    {
        $eGeoRegion = new Marktjagd_Database_Entity_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();
        $mGeoRegion->findRegionByZipCode($zipCode, $localCode, $eGeoRegion);

        return $eGeoRegion->getState();
    }

    /**
     * Ermittelt anhand der übergebenen Postleitzahl die entsprechende Stadt
     *
     * @param string $zipCode
     * @return string
     */
    public function findCityByZipCode($zipCode, $localCode = 'DE')
    {
        $eGeoRegion = new Marktjagd_Database_Entity_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();
        $mGeoRegion->findRegionByZipCode($zipCode, $localCode, $eGeoRegion);

        return $eGeoRegion->getCity();
    }

    public function findZipCodeByCity($city, $localCode = 'DE')
    {
        $eGeoRegion = new Marktjagd_Database_Entity_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();
        $mGeoRegion->findRegionByCity($city, $localCode, $eGeoRegion);

        return $eGeoRegion->getZipCode();
    }

    public function findZipCodesForCity($city, $localCode = 'DE')
    {
        $cGeoRegion = new Marktjagd_Database_Collection_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();

        $mGeoRegion->findZipcodesForCity($city, $localCode, $cGeoRegion);
        $aZipcodes = [];
        foreach ($cGeoRegion as $geoEntry) {
            $aZipcodes[$geoEntry->getZipCode()] = [
                'latitude' => $geoEntry->getLatitude(),
                'longitude' => $geoEntry->getLongitude()
            ];
        }

        return $aZipcodes;
    }

    /**
     * Ermittelt anhand der übergebenen Postleitzahl das entsprechende Bundesland und gibt dessen Kürzel zurück
     *
     * @param $zipCode
     * @return string
     */
    public function findShortRegionByZipCode($zipCode, $localCode = 'DE')
    {
        $aMapRegionToShortCode = array(
            'Baden-Württemberg' => 'BW',
            'Bayern' => 'BY',
            'Berlin' => 'BE',
            'Brandenburg' => 'BB',
            'Bremen' => 'HB',
            'Hamburg' => 'HH',
            'Hessen' => 'HE',
            'Mecklenburg-Vorpommern' => 'MV',
            'Niedersachsen' => 'NI',
            'Nordrhein-Westfalen' => 'NW',
            'Rheinland-Pfalz' => 'RP',
            'Saarland' => 'SL',
            'Sachsen' => 'SN',
            'Sachsen-Anhalt' => 'ST',
            'Schleswig-Holstein' => 'SH',
            'Thüringen' => 'TH',
        );

        $regionShort = '';
        $region = $this->findRegionByZipCode($zipCode, $localCode);
        if (array_key_exists($region, $aMapRegionToShortCode)) {
            $regionShort = $aMapRegionToShortCode[$region];
        }

        return $regionShort;
    }

    /**
     * Ermittelt anhand der übergebenen Postleitzahl, ob sich der Ort im Osten oder Westen Deutschlands befindet
     *
     * @param $zipCode
     * @return string
     */
    public function findEastWestByZipCode($zipCode, $localCode = 'DE')
    {
        $aMapRegionToShortCode = array(
            'Baden-Württemberg' => 'West',
            'Bayern' => 'West',
            'Berlin' => 'Ost',
            'Brandenburg' => 'Ost',
            'Bremen' => 'West',
            'Hamburg' => 'West',
            'Hessen' => 'West',
            'Mecklenburg-Vorpommern' => 'Ost',
            'Niedersachsen' => 'West',
            'Nordrhein-Westfalen' => 'West',
            'Rheinland-Pfalz' => 'West',
            'Saarland' => 'West',
            'Sachsen' => 'Ost',
            'Sachsen-Anhalt' => 'Ost',
            'Schleswig-Holstein' => 'West',
            'Thüringen' => 'Ost',
        );

        $regionShort = '';
        $region = $this->findRegionByZipCode($zipCode, $localCode);
        if (array_key_exists($region, $aMapRegionToShortCode)) {
            $regionShort = $aMapRegionToShortCode[$region];
        }

        return $regionShort;
    }

    /**
     * Liefert Postleitzahlen aus Deutschland anhand der übergebenen Maschengröße
     *
     * @param int|bool $netSize
     * @return array
     */
    public function findZipCodesByNetSize($netSize = false, $withGeolocations = false, $localCode = 'DE')
    {
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();

        if ($withGeolocations) {
            $aZipCodes = $mGeoRegion->findZipCodesByNetSizeWithGeolocation($netSize, $localCode);
        } else {
            $aZipCodes = $mGeoRegion->findZipCodesByNetSize($netSize, $localCode);
        }

        return $aZipCodes;
    }

    /**
     * Liefert alle Postleitzahlen aus Deutschland
     *
     * @return array
     */
    public function findAllZipCodes($localCode = 'DE')
    {
        return $this->findZipCodesByNetSize(FALSE, FALSE, $localCode);
    }

    /**
     *
     * @param type $aCounties
     * @return \Marktjagd_Database_Collection_GeoRegion
     */
    public function findZipCodesForCounty($aCounties, $localCode = 'DE')
    {
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();
        foreach ($aCounties as $singleCounty) {
            $cGeoRegion = new Marktjagd_Database_Collection_GeoRegion();
            $mGeoRegion->findZipCodesForCounty($singleCounty, $localCode, $cGeoRegion);

            $aReturnZipCodes[$singleCounty] = $cGeoRegion;
        }

        return $aReturnZipCodes;
    }

    public function findAll($localCode = 'DE')
    {
        $cGeoRegion = new Marktjagd_Database_Collection_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();

        if (!strlen($localCode)) {
            $mGeoRegion->fetchAll(null, $cGeoRegion);
        } else {
            $mGeoRegion->findAll($localCode, $cGeoRegion);
        }

        return $cGeoRegion;
    }

    public function findAllZipcodesForParis()
    {
        $cGeoRegion = new Marktjagd_Database_Collection_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();

        $mGeoRegion->findAllZipcodesForParis($cGeoRegion);

        return $cGeoRegion;
    }

    public function findAllZipcodesForParisSurroundingDepartments()
    {
        $cGeoRegion = new Marktjagd_Database_Collection_GeoRegion();
        $mGeoRegion = new Marktjagd_Database_Mapper_GeoRegion();

        $mGeoRegion->findAllZipcodesForParisSurroundingDepartments($cGeoRegion);

        return $cGeoRegion;
    }
}
