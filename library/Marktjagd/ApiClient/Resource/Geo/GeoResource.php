<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the GeoResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  geo
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Geo;

use Marktjagd\ApiClient\Resource;
use Marktjagd\ApiClient\Request\Request;

/**
 * Geo resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  geo
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class GeoResource extends Resource\Resource
{

    const
        TYPE_ADDRESS = 'address',
        TYPE_REGION = 'region',
        TYPE_LOCATION = 'location';

    protected static $hasCollection = true;

    /**
     * Load a geo resource object.
     *
     * @param string $type      geo type (address, region, location)
     * @param int    $typeId    geo type id
     * @param array  $params    request parameters
     * @return Resource the resource object or NULL if the request failed
     */
    public static function find($type, $typeId, array $params = array())
    {
        return parent::doFind("$type/$typeId", $params);
    }

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldString('type'), true, Request::METHOD_GET)
            ->hasField(new Resource\ResourceFieldInteger('type_id'), true, Request::METHOD_GET)
            ->hasField(new Resource\ResourceFieldFloat('longitude'))
            ->hasField(new Resource\ResourceFieldFloat('latitude'))
            ->hasField(new Resource\ResourceFieldInteger('region_id'))
            ->hasField(new Resource\ResourceFieldInteger('city_id'))
            ->hasField(new Resource\ResourceFieldString('url_name'))
            ->hasField(new Resource\ResourceFieldString('location'))
            ->hasField(new Resource\ResourceFieldString('city'))
            ->hasField(new Resource\ResourceFieldString('zipcode'))
            ->hasField(new Resource\ResourceFieldString('street'))
            ->hasField(new Resource\ResourceFieldString('street_number'))
            ->hasField(new Resource\ResourceFieldString('ip'));
    }

}
