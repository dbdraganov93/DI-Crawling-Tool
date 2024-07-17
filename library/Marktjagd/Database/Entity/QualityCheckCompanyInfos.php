
<?php

class Marktjagd_Database_Entity_QualityCheckCompanyInfos extends Marktjagd_Database_Entity_Abstract {

    protected $_idQualityCheckCompanyInfos;
    protected $_idCompany;
    protected $_stores;
    protected $_brochures;
    protected $_products;
    protected $_limitStores;
    protected $_limitBrochures;
    protected $_limitProducts;
    protected $_freshnessStores;
    protected $_freshnessProducts;
    protected $_freshnessBrochures;
    protected $_futureBrochures;
    protected $_futureProducts;
    protected $_oCompany;
    
    protected $_aColumnMap = array(
        'idQualityCheckCompanyInfos' => 'IdQualityCheckCompanyInfos',
        'idCompany' => 'IdCompany',
        'stores' => 'Stores',
        'brochures' => 'Brochures',
        'products' => 'Products',
        'limitStores' => 'LimitStores',
        'limitProducts' => 'LimitProducts',
        'limitBrochures' => 'LimitBrochures',
        'freshnessStores' => 'FreshnessStores',
        'freshnessProducts' => 'FreshnessProducts',
        'freshnessBrochures' => 'FreshnessBrochures',
        'futureBrochures' => 'FutureBrochures',
        'futureProducts' => 'FutureProducts'
    );
    
    protected $_aRelationMap = array('Company' => 'Marktjagd_Database_Entity_Company');
    
    protected $_aRelationPropertyMap = array('Company' => 'Company');

    public function getMapper() {
        return parent::getMapper();
    }
    
    public function getIdQualityCheckCompanyInfos() {
        return $this->_idQualityCheckCompanyInfos;
    }

    public function getIdCompany() {
        return $this->_idCompany;
    }

    public function getStores() {
        return $this->_stores;
    }

    public function getBrochures() {
        return $this->_brochures;
    }

    public function getProducts() {
        return $this->_products;
    }
    
    public function getLimitStores() {
        return $this->_limitStores;
    }
    
    public function getLimitBrochures() {
        return $this->_limitBrochures;
    }
    
    public function getLimitProducts() {
        return $this->_limitProducts;
    }
    
    public function getFreshnessStores() {
        return $this->_freshnessStores;
    }

    public function getFreshnessProducts() {
        return $this->_freshnessProducts;
    }
    
    public function getFreshnessBrochures() {
        return $this->_freshnessBrochures;
    }
    
    public function getFutureBrochures() {
        return $this->_futureBrochures;
    }
    
    public function getFutureProducts() {
        return $this->_futureProducts;
    }

    public function getCompany() {
        return $this->_oCompany;
    }

    public function setIdQualityCheckCompanyInfos($idQualityCheckCompanyInfos) {
        $this->_idQualityCheckCompanyInfos = $idQualityCheckCompanyInfos;
        return $this;
    }

    public function setIdCompany($idCompany) {
        $this->_idCompany = $idCompany;
        return $this;
    }

    public function setStores($stores) {
        $this->_stores = $stores;
        return $this;
    }

    public function setBrochures($brochures) {
        $this->_brochures = $brochures;
        return $this;
    }

    public function setProducts($products) {
        $this->_products = $products;
        return $this;
    }
    
    public function setLimitStores($limitStores) {
        $this->_limitStores = $limitStores;
        return $this;
    }
    
    public function setLimitBrochures($limitBrochures) {
        $this->_limitBrochures = $limitBrochures;
        return $this;
    }
    
    public function setLimitProducts($limitProducts) {
        $this->_limitProducts = $limitProducts;
        return $this;
    }
    
    public function setFreshnessStores($freshnessStores) {
        $this->_freshnessStores = $freshnessStores;
        return $this;
    }

    public function setFreshnessProducts($freshnessProducts) {
        $this->_freshnessProducts = $freshnessProducts;
        return $this;
    }
    
    public function setFreshnessBrochures($freshnessBrochures) {
        $this->_freshnessBrochures = $freshnessBrochures;
        return $this;
    }
    
    public function setFutureBrochures($futureBrochures) {
        $this->_futureBrochures = $futureBrochures;
        return $this;
    }
    
    public function setFutureProducts($futureProducts) {
        $this->_futureProducts = $futureProducts;
        return $this;
    }

    public function setCompany(Marktjagd_Database_Entity_Company $oCompany) {
        $this->_oCompany = $oCompany;
        return $this;
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert
     * @return void
     */
    public function save($bNull = false, $bForceInsert = false) {
        $this->getMapper()->save($this, $bNull, $bForceInsert);
    }

    /**
     * Loads the data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId) {
        return $this->getMapper()->find($mId, $this);
    }

}
