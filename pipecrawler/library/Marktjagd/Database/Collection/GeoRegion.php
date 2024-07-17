<?php

class Marktjagd_Database_Collection_GeoRegion extends Marktjagd_Database_Collection_Abstract
{
    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return Marktjagd_Database_Mapper_GeoRegion
     */
    public function getMapper()
    {
      return parent::getMapper();
    }

}