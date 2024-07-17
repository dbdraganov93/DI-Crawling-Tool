<?php

abstract class Marktjagd_Entity_Api_Abstract
{
    abstract function getHash();
    
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }
    
    public function getProperties()
    {
        return get_class_vars(get_class($this));
    }    
}