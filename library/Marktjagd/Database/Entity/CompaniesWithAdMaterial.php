<?php

class Marktjagd_Database_Entity_CompaniesWithAdMaterial extends Marktjagd_Database_Entity_Abstract
{
    protected $_idCompaniesWithAdMaterial;
    protected $_idCompany;
    protected $_percentageStoresWithBrochures;
    protected $_percentageStoresWithProducts;
    protected $_lastTimeChecked;
    protected $_oCompany;

    protected $_aColumnMap = array(
        'idCompaniesWithAdMaterial' => 'IdCompaniesWithAdMaterial',
        'idCompany' => 'IdCompany',
        'percentageStoresWithBrochures' => 'PercentageStoresWithBrochures',
        'percentageStoresWithProducts' => 'PercentageStoresWithProducts',
        'lastTimeChecked' => 'LastTimeChecked'
    );
    
    protected $_aRelationMap = array('Company' => 'Marktjagd_Database_Entity_Company');
    
    protected $_aRelationPropertyMap = array('Company' => 'Company');
    
    public function getIdCompaniesWithAdMaterial()
    {
        return $this->_idCompaniesWithAdMaterial;
    }

    public function getIdCompany()
    {
        return $this->_idCompany;
    }

    public function getPercentageStoresWithBrochures()
    {
        return $this->_percentageStoresWithBrochures;
    }

    public function getPercentageStoresWithProducts()
    {
        return $this->_percentageStoresWithProducts;
    }

    public function getLastTimeChecked()
    {
        return $this->_lastTimeChecked;
    }
    
    public function getCompany() {
        return $this->_oCompany;
    }

    public function setIdCompaniesWithAdMaterial($idCompaniesWithAdMaterial)
    {
        $this->_idCompaniesWithAdMaterial = $idCompaniesWithAdMaterial;
        return $this;
    }

    public function setIdCompany($idCompany)
    {
        $this->_idCompany = $idCompany;
        return $this;
    }

    public function setPercentageStoresWithBrochures($percentageStoresWithBrochures)
    {
        $this->_percentageStoresWithBrochures = $percentageStoresWithBrochures;
        return $this;
    }

    public function setPercentageStoresWithProducts($percentageStoresWithProducts)
    {
        $this->_percentageStoresWithProducts = $percentageStoresWithProducts;
        return $this;
    }

    public function setLastTimeChecked($lastTimeChecked)
    {
        $this->_lastTimeChecked = $lastTimeChecked;
        return $this;
    }
    
    public function setCompany(Marktjagd_Database_Entity_Company $oCompany) {
        $this->_oCompany = $oCompany;
        return $this;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_CompaniesWithAdMaterial
     */
    public function getMapper()
    {
        return parent::getMapper();
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     * @return int|mixed
     */
    public function save($bNull = false)
    {
        return $this->getMapper()->save($this, $bNull);
    }

    /**
     * Loads the data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @return bool True if found, otherwise false
     */
    public function find($mId)
    {
        return $this->getMapper()->find($mId, $this);
    }

}