<?php

class Marktjagd_Database_Mapper_Zipcode extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_Zipcode
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Ermittelt alle Postleitzahlen aus Deutschland
     *
     * @param int $digits
     * @return array|bool
     */
    public function findAllZipcodes($digits=5)
    {
        $zipCodes = array();
        $rows = $this->getDbTable()->findAllZipcodes($digits);

        if (!count($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            $zipCodes[$row['zipcode']] = $row['zipcode'];
        }

        return $zipCodes;
    }

    /**
     * Liefert ein Array mit Postleitzahlen aus Deutschland die sich auf einem
     * Gitter mit der übergebenen Maschengröße befinden.
     *
     * @param $netSize
     * @return array|bool
     */
    public function findZipcodeByGrid($netSize)
    {
        $zipCodes = array();
        $rows = $this->getDbTable()->findZipcodeByGrid($netSize);

        if (!count($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            $zipCodes[$row['region_zipcode']] = $row['region_zipcode'];
        }

        return $zipCodes;
    }

    /**
     * Ermittelt umliegende Postleitzahlen, ausgehend von der PLZ und der Entfernung
     *
     * @param $zipCode
     * @param $maxDistance
     * @return array|bool
     */
    public function findNeighborhoodZipcodes($zipCode, $maxDistance)
    {
        $zipCodes = array();
        $rows = $this->getDbTable()->findNeighborhoodZipcodes($zipCode, $maxDistance);

        if (!count($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            $zipCodes[$row['region_zipcode']] = $row['region_zipcode'];
        }

        return $zipCodes;
    }
}