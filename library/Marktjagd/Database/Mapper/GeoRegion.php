<?php

class Marktjagd_Database_Mapper_GeoRegion extends Marktjagd_Database_Mapper_Abstract
{

    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_GeoRegion
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_GeoRegion $oGeoRegion Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_GeoRegion $oGeoRegion)
    {
        return parent::_find($mId, $oGeoRegion);
    }

    /**
     * Ermittelt anhand der übergebenen Postleitzahl das entsprechende Bundesland
     *
     * @param $zipCode
     * @param Marktjagd_Database_Entity_GeoRegion $oGeoRegion
     * @return bool
     */
    public function findRegionByZipCode($zipCode, $localCode, Marktjagd_Database_Entity_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findRegionByZipCode($zipCode, $localCode);
        if ($result) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }

    public function findRegionByCity($city, $localCode, Marktjagd_Database_Entity_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findRegionByCity($city, $localCode);
        if ($result) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }

    public function findZipcodesForCity($city, $localCode, Marktjagd_Database_Collection_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findZipcodesForCity($city, $localCode);
        if ($result) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Liefert Postleitzahlen aus Deutschland anhand der übergebenen Maschengröße
     *
     * @param int $netSize
     * @return array
     */
    public function findZipCodesByNetSize($netSize, $localCode)
    {
        $aZip = array();
        if ($netSize) {
            $sAddress = new Marktjagd_Service_Text_Address();
            $aZip = $sAddress->getRegionGrid($netSize, $localCode);
        } else {
            $result = $this->getDbTable()->findZipCodesByNetSize();
            $identifier = 'zipcode';
            foreach ($result as $geoEntry) {
                if (strlen($localCode) && preg_match('#' . $localCode . '#i', $geoEntry['local_code'])) {
                    $aZip[$geoEntry[$identifier]] = $geoEntry[$identifier];
                } elseif (!strlen($localCode)) {
                    $aZip[$geoEntry[$identifier]] = $geoEntry[$identifier];
                }
            }
        }

        return $aZip;
    }

    /**
     * Liefert Postleitzahlen aus Deutschland anhand der übergebenen Maschengröße
     * inkl. der zugehörigen Geokoordinaten
     *
     * @param int $netSize
     * @return array
     */
    public function findZipCodesByNetSizeWithGeolocation($netSize, $localCode)
    {
        $aZip = array();
        if ($netSize) {
            $sAddress = new Marktjagd_Service_Text_Address();
            $aZip = $sAddress->getRegionGrid($netSize, $localCode, TRUE);
        } else {
            $result = $this->getDbTable()->findZipCodesByNetSize($localCode);
            $identifier = 'geo_region.region_zipcode';
            foreach ($result as $geoEntry) {
                $aZip[$geoEntry[$identifier]] = array('zip' => $geoEntry[$identifier], 'lat' => $geoEntry['geo_region.region_latitude'], 'lng' => $geoEntry['geo_region.region_longitude'], 'city' => 'geo_region.region_city');
            }
        }

        return $aZip;
    }

    /**
     *
     * @param type $county
     * @param Marktjagd_Database_Collection_GeoRegion $oGeoRegion
     * @return boolean
     */
    public function findZipCodesForCounty($county, $localCode, Marktjagd_Database_Collection_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findZipCodesForCounty($county, $localCode);

        if (count($result) > 0) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }

    public function findAll($localCode, Marktjagd_Database_Collection_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findAllByCountry($localCode);

        if (count($result) > 0) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }

    public function findAllZipcodesForParis(Marktjagd_Database_Collection_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findAllZipcodesForParis();

        if (count($result) > 0) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }

    public function findAllZipcodesForParisSurroundingDepartments(Marktjagd_Database_Collection_GeoRegion $oGeoRegion)
    {
        $result = $this->getDbTable()->findAllZipcodesForParisSurroundingDepartments();

        if (count($result) > 0) {
            $oGeoRegion->setOptions($result);
            return true;
        }

        return false;
    }
}
