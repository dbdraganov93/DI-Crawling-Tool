<?php

class Marktjagd_Database_Mapper
{
    /**
     * Creates a mapper object.
     *
     * @param mixed	$mMapper Name or object (Marktjagd_Database_Abstract, Marktjagd_Database_Collection_Abstract)
     * @throws Marktjagd_Database_Exception
     *
     * @return Marktjagd_Database_Mapper_Abstract
     */
    public static function factory($mMapper)
    {
        // get mapper name by class name
        if ($mMapper instanceof Marktjagd_Database_Entity_Abstract) {
            $mMapper = str_replace('Entity', 'Mapper', get_class($mMapper));
        } elseif ($mMapper instanceof Marktjagd_Database_Collection_Abstract) {
            $mMapper = str_replace('Collection', 'Mapper', get_class($mMapper));
        } elseif (is_string($mMapper)) {
            $mMapper = 'Marktjagd_Database_Mapper_' . $mMapper;
        }

        if (!is_string($mMapper)) {
            throw new Marktjagd_Database_Exception('Invalid data mapper provided');
        }

        return new $mMapper();
    }
}