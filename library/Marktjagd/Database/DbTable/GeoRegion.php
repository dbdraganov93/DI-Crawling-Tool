<?php

class Marktjagd_Database_DbTable_GeoRegion extends Marktjagd_Database_DbTable_Abstract
{

    protected $_name = 'geo_region';
    protected $_primary = 'region_id';

    /**
     * Ermittelt anhand der übergebenen Postleitzahl das entsprechende Bundesland
     *
     * @param $zipCode
     * @return Zend_Db_Table_Row_Abstract
     */
    public function findRegionByZipCode($zipCode, $localCode)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
            ->where('region_zipcode = ?', (string)$zipCode)
            ->where('local_code = ?', (string)$localCode);

        return $this->fetchRow($select);
    }

    public function findRegionByCity($city, $localCode)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
            ->where('region_city LIKE ?', (string)$city . '%')
            ->where('local_code = ?', (string)$localCode);

        return $this->fetchRow($select);
    }

    public function findZipcodesForCity($city, $localCode)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
            ->where('region_city = ?', (string)$city)
            ->where('local_code = ?', (string)$localCode);

        return $this->fetchAll($select);
    }

    /**
     * Liefert Postleitzahlen aus Deutschland anhand der übergebenen Maschengröße
     *
     * @param $localCode
     * @return array
     */
    public function findZipCodesByNetSize()
    {
        $stmt = $this->_db->query('CALL getAllZipcodes(5)');
        $data = $stmt->fetchAll();

        return $data;
    }

    public function findZipCodesForCounty($county, $localCode)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
            ->where('region_state = ?', (string)$county)
            ->where('local_code = ?', (string)$localCode);

        return $this->fetchAll($select);
    }

    public function findAllByCountry($localCode = 'DE')
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
            ->where('local_code = ?', (string)$localCode);

        return $this->fetchAll($select);
    }

    public function findAllZipcodesForParis()
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where('local_code = ?', 'FR')
            ->where('region_city LIKE ?', 'Paris%')
            ->where('region_zipcode LIKE ?', '75%');

        return $this->fetchAll($select);
    }

    public function findAllZipcodesForParisSurroundingDepartments()
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where('local_code = ?', 'FR');
        $select->where('region_zipcode LIKE ?', '92%')
            ->orWhere('region_zipcode LIKE ?', '93%')
            ->orWhere('region_zipcode LIKE ?', '94%');

        return $this->fetchAll($select);
    }
}
