<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the CityZipcodeResource class.
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
 * City zipcode resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  geo
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class CityZipcodeResource extends Resource\Resource
{

    protected static $hasCollection = true;

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasAttribute(new Resource\ResourceAttributeInteger('store_hits'))

            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldInteger('city_id'))
            ->hasField(new Resource\ResourceFieldString('zipcode'))
            ->hasField(new Resource\ResourceFieldString('city'))
            ->hasField(new Resource\ResourceFieldString('county'))
            ->hasField(new Resource\ResourceFieldString('state'))
            ->hasField(new Resource\ResourceFieldFloat('longitude'))
            ->hasField(new Resource\ResourceFieldFloat('latitude'))
            ->hasField(new Resource\ResourceFieldFloat('distance'));
    }

}
