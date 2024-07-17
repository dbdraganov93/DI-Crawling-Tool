<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the HourCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  store
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Store;

use Marktjagd\ApiClient\Resource;

/**
 * Hour collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  store
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class HourCollectionResource extends Resource\CollectionResource
{

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {

    }

    /**
     * @see Resource::fromArray()
     */
    public function fromArray(array $values = array())
    {
        $resourceName = self::getResourceName();

        $this->position = 0;
        $this->resources[$resourceName] = array();

        if (!empty($values[$resourceName]))
        {
            foreach ($values[$resourceName] as $v)
            {
                $item = Resource\ResourceFactory::create($resourceName);
                $item->fromArray($v);
                $this->resources[$resourceName][] = $item;
            }
        }
        
        return $this;
    }

    /**
     * @see Resource::toArray
     */
    public function toArray()
    {
        $array = parent::toArray();
        $resourceName = self::getResourceName();

        if (empty($this->resources[$resourceName]))
        {
            return null;
        }

        $resources = array();

        foreach ($this->resources[$resourceName] as $resource)
        {
            $resources[] = $resource->toArray();
        }

        $array[$resourceName] = $resources;

        return $array;
    }

    /**
     * @see Resource::offsetGet
     */
    public function offsetSet($index, $value)
    {
        if (is_string($index))
        {
            return parent::offsetSet($index, $value);
        }

        if (!is_null($index))
        {
            throw new Resource\ResourceException('Setting a specific index is not allowed');
        }

        if (!$value instanceof HourResource)
        {
            throw new Resource\ResourceException('Object of type HourResource expected, ' . gettype($value) . ' given');
        }

        $this->resources[self::getResourceName()][] = $value;
    }

}
