<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the MallResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  mall
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Mall;

use Marktjagd\ApiClient\Resource;

/**
 * Mall collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  mall
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class MallResource extends Resource\Resource
{

    const
        STATUS_VISIBLE = 'visible',
        STATUS_HIDDEN  = 'hidden';

    protected static $hasCollection = true;

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasAttribute(new Resource\ResourceAttributeInteger('article_hits'))
            ->hasAttribute(new Resource\ResourceAttributeInteger('brochure_hits'))
            ->hasAttribute(new Resource\ResourceAttributeInteger('offer_hits'))
            ->hasAttribute(new Resource\ResourceAttributeInteger('store_hits'))

            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldString('description'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldString('zipcode'))
            ->hasField(new Resource\ResourceFieldString('city'))
            ->hasField(new Resource\ResourceFieldString('street'))
            ->hasField(new Resource\ResourceFieldString('street_number'))
            ->hasField(new Resource\ResourceFieldFloat('latitude'))
            ->hasField(new Resource\ResourceFieldFloat('longitude'))
            ->hasField(new Resource\ResourceFieldInteger('address_id'))
            ->hasField(new Resource\ResourceFieldInteger('city_id'))
            ->hasField(new Resource\ResourceFieldString('email'))
            ->hasField(new Resource\ResourceFieldString('fax_number'))
            ->hasField(new Resource\ResourceFieldString('phone_number'))
            ->hasField(new Resource\ResourceFieldString('homepage'))
            ->hasField(new Resource\ResourceFieldString('hours_text'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldFloat('distance'))

            ->hasResource(Resource\ResourceFactory::create('rating'))
            ->hasResource(Resource\ResourceFactory::create('hours'))
            ->hasResource(Resource\ResourceFactory::create('images'))
            ->hasResource(Resource\ResourceFactory::create('geos'));
    }

    /**
     * Sets the embedded hours resource.
     *
     * @param HourCollectionResource $hours
     */
    public function setHours(Resource\Store\HourCollectionResource $hours)
    {
        $this->resources['hours'] = $hours;
        $this->isModified = true;
        
        return $this;
    }

    /**
     * Loads the resource fields from an assoziative array.
     *
     * @param array $values field values
     * @return Resource current instance
     */
    public function fromArray(array $values = array())
    {
        parent::fromArray($values);

        if (isset($values['hours']))
        {
            $hours = Resource\ResourceFactory::create('hours');
            $hours->fromArray($values['hours']);
            $this->setHours($hours);
        }

        return $this;
    }

    /**
     * Returns an assoziative array with the values of the resource fields.
     *
     * @return array resource field values
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['hours'] = $this->resources['hours']->toArray();

        return $array;
    }

}
