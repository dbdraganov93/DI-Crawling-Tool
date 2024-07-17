<?php

abstract class Marktjagd_Database_Collection_Abstract extends Marktjagd_Database_Abstract implements Iterator, Countable, ArrayAccess
{
    /**
     * Saves the number of items
     *
     * @var int
     */
    protected $_iCount;

    /**
     * Saves the total number of items
     *
     * @var int
     */
    protected $_iTotalCount;

    /**
     * Saves the objects
     *
     * @var array
     */
    protected $_aData = array();

    /**
     * Creates the objects.
     *
     * @param array|Zend_Db_Table_Rowset_Abstract $mResults    Rowset or array with data
     * @param string $sIndex           Value of object property which should be used as index
     *
     * @return Marktjagd_Database_Collection_Abstract
     */
    public function __construct($mResults = null, $sIndex = '')
    {
        if (is_null($mResults)) {
            return;
        }

        $this->setOptions($mResults, $sIndex);
    }

    /**
     * Creates the objects
     *
     * @param array|Zend_Db_Table_Rowset_Abstract $mResults Rowset or array with data
     * @param string $sIndex Value of object property which should be used as index
     *
     * @throws Marktjagd_Database_Collection_Exception
     * @return void
     */
    public function setOptions($mResults, $sIndex = '')
    {
        if (!$mResults instanceof Zend_Db_Table_Rowset_Abstract
            && !is_array($mResults)) {
            throw new Marktjagd_Database_Collection_Exception('Invalid data. Result must be an array or an instance of Zend_Db_Table_Rowset_Abstract.');
        }

        foreach ($mResults as $mData)
        {
            // use obbject property as index
            if ($sIndex) {
                // create object
                $oData = Marktjagd_Database_Entity::factory($this, $mData);
                $this->_aData[$oData->$sIndex] = $oData;
            } else {
                // create object
                $this->_aData[] = Marktjagd_Database_Entity::factory($this, $mData);
            }
        }
    }

    /**
     * Returns the total number of items available.
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->_iTotalCount;
    }

    /**
     * Sets the total number of items available.
     *
     * @param $iValue
     * @return int
     */
    public function setTotalCount($iValue)
    {
        $this->_iTotalCount = (int) $iValue;
        return $this;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_Abstract
     */
    public function getMapper()
    {
        return parent::getMapper();
    }

    /**
     * Returns the number of objects.
     *
     * @return int
     */
    public function count()
    {
        if (null === $this->_iCount) {
            $this->_iCount = count($this->_aData);
        }

        return $this->_iCount;
    }

    /**
     * Returns current object.
     *
     * @return Marktjagd_Database_Entity_Abstract Object, otherwise false
     */
    public function current()
    {
        if ($this->_aData instanceof Iterator) {
            $key = $this->_aData->key();
        } else {
            $key = key($this->_aData);
        }

        return $this->_aData[$key];
    }

    /**
     * Returns the key of the current object.
     *
     * @return Marktjagd_Database_Entity_Abstract Object, otherwise false
     */
    public function key()
    {
        return key($this->_aData);
    }

    /**
     * Returns next object.
     *
     * @return Marktjagd_Database_Entity_Abstract Object, otherwise false
     */
    public function next()
    {
        return next($this->_aData);
    }

    /**
     * Returns the first element.
     *
     * @return Marktjagd_Database_Entity_Abstract Object, otherwise false
     */
    public function rewind()
    {
        return reset($this->_aData);
    }

    /**
     * Checks if object is set.
     *
     * @return bool True if set, otherwise false
     */
    public function valid()
    {
        $mData = key($this->_aData);

        return isset($mData);
    }

    /**
     * Checks if offset exists.
     *
     * @param mixed $offset Offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->_aData[$offset]);
    }

    /**
     * Returns element on this offset.
     *
     * @param mixed $offset Offset
     *
     * @return Marktjagd_Database_Entity_Abstract
     */
    public function offsetGet($offset)
    {
        return $this->_aData[$offset];
    }

    /**
     * Sets data to this offset.
     *
     * @param mixed $offset Offset
     * @param mixed $value Value
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_aData[] = $value;
            return;
        }

        $this->_aData[$offset] = $value;
    }

    /**
     * Unset data by this offset.
     *
     * @param mixed $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->_aData[$offset]);
    }
}