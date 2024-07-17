<?php

abstract class Marktjagd_Database_Abstract
{
    /**
     * Saves the data mapper
     *
     * @var Marktjagd_Database_Mapper_Abstract
     */
    protected $_oMapper;

    /**
     * Table prefix
     *
     * @var string
     */
    protected $_sTablePrefix;

    /**
     * Unsets mapper, is not needed by serialization.
     *
     * @return array
     */
    public function __sleep()
    {
        $this->resetMapper();

        return array();
    }

    /**
     * Unset mapper object.
     *
     * @return Marktjagd_Database_Abstract
     */
    public function resetMapper()
    {
        $this->_oMapper = null;
        return $this;
    }

    /**
     * Sets the mapper class.
     *
     * @param mixed $mMapper Name or mapper object
     *
     * @return $this
     */
    public function setMapper($mMapper)
    {
        if (!$mMapper instanceof Marktjagd_Database_Mapper_Abstract) {
            $mMapper = Marktjagd_Database_Mapper::factory($mMapper);
        }

        $this->_oMapper = $mMapper;
        return $this;
    }

    /**
     * Returns the mapper class. If no one exists,
     * default will be created.
     *
     * @return Marktjagd_Database_Mapper_Abstract
     */
    public function getMapper()
    {
        if (null === $this->_oMapper) {
            $this->setMapper($this);
        }

        return $this->_oMapper;
    }
}