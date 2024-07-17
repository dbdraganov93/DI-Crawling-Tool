<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the StoreResource class.
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
 * Store collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  store
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class StoreResource extends Resource\Resource
{

    const
        STATUS_VISIBLE = 'visible',
        STATUS_INVALID = 'invalid',
        STATUS_REMOVED = 'removed';

    protected static $hasCollection = true;

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldString('partner'))
            ->hasField(new Resource\ResourceFieldString('number'))
            ->hasField(new Resource\ResourceFieldBoolean('number_is_generated'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldInteger('company_id'))
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldString('subtitle'))
            ->hasField(new Resource\ResourceFieldString('description'))
            ->hasField(new Resource\ResourceFieldInteger('address_id'))
            ->hasField(new Resource\ResourceFieldInteger('city_id'))
            ->hasField(new Resource\ResourceFieldString('street'))
            ->hasField(new Resource\ResourceFieldString('street_number'))
            ->hasField(new Resource\ResourceFieldString('zipcode'))
            ->hasField(new Resource\ResourceFieldString('city'))
            ->hasField(new Resource\ResourceFieldFloat('longitude'))
            ->hasField(new Resource\ResourceFieldFloat('latitude'))
            ->hasField(new Resource\ResourceFieldString('payment'))
            ->hasField(new Resource\ResourceFieldString('category'))
            ->hasField(new Resource\ResourceFieldString('homepage'))
            ->hasField(new Resource\ResourceFieldString('email'))
            ->hasField(new Resource\ResourceFieldString('phone_number'))
            ->hasField(new Resource\ResourceFieldString('fax_number'))
            ->hasField(new Resource\ResourceFieldString('parking'))
            ->hasField(new Resource\ResourceFieldBoolean('barrier_free'))
            ->hasField(new Resource\ResourceFieldString('bonus_card'))
            ->hasField(new Resource\ResourceFieldString('section'))
            ->hasField(new Resource\ResourceFieldString('service'))
            ->hasField(new Resource\ResourceFieldBoolean('toilet'))
            ->hasField(new Resource\ResourceFieldBoolean('has_articles'))
            ->hasField(new Resource\ResourceFieldBoolean('has_brochures'))
            ->hasField(new Resource\ResourceFieldBoolean('has_coupons'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldString('datetime_modified'))
            ->hasField(new Resource\ResourceFieldString('datetime_removed'))
            ->hasField(new Resource\ResourceFieldString('hours_text'))
            ->hasField(new Resource\ResourceFieldFloat('distance'))
            ->hasField(new Resource\ResourceFieldFloat('score'))
            ->hasField(new Resource\ResourceFieldInteger('num_others'))
            ->hasField(new Resource\ResourceFieldBoolean('has_partner_priority'))
            ->hasField(new Resource\ResourceFieldArrayString('tracking_bugs'))
            ->hasField(new Resource\ResourceFieldString('external_tracking_id'))

            ->hasResource(Resource\ResourceFactory::create('industry'))
            ->hasResource(Resource\ResourceFactory::create('rating'))
            ->hasResource(Resource\ResourceFactory::create('tags'))
            ->hasResource(Resource\ResourceFactory::create('hours'))
            ->hasResource(Resource\ResourceFactory::create('images'));
    }

    /**
     * Sets the embedded hours resource.
     *
     * @param HourCollectionResource $hours
     */
    public function setHours(HourCollectionResource $hours)
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
