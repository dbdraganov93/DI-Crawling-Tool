<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the CompanyResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  company
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Company;

use Marktjagd\ApiClient\Resource;

/**
 * Company resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  company
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class CompanyResource extends Resource\Resource
{

    const
        STATUS_ADDED = 'added',
        STATUS_HIDDEN = 'hidden',
        STATUS_VISIBLE = 'visible';

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
            ->hasAttribute(new Resource\ResourceAttributeBoolean('favored'))

            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldString('partner'))
            ->hasField(new Resource\ResourceFieldString('number'))
            ->hasField(new Resource\ResourceFieldInteger('product_id'))
            ->hasField(new Resource\ResourceFieldInteger('product_temp_id'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldString('slogan'))
            ->hasField(new Resource\ResourceFieldString('legal_form'))
            ->hasField(new Resource\ResourceFieldInteger('industry_id'))
            ->hasField(new Resource\ResourceFieldInteger('price_level'))
            ->hasField(new Resource\ResourceFieldString('description'))
            ->hasField(new Resource\ResourceFieldString('street'))
            ->hasField(new Resource\ResourceFieldString('street_number'))
            ->hasField(new Resource\ResourceFieldString('zipcode'))
            ->hasField(new Resource\ResourceFieldString('city'))
            ->hasField(new Resource\ResourceFieldString('category'))
            ->hasField(new Resource\ResourceFieldString('homepage'))
            ->hasField(new Resource\ResourceFieldString('facebook_url'))
            ->hasField(new Resource\ResourceFieldString('google_plus_url'))
            ->hasField(new Resource\ResourceFieldString('email'))
            ->hasField(new Resource\ResourceFieldString('phone_number'))
            ->hasField(new Resource\ResourceFieldString('fax_number'))
            ->hasField(new Resource\ResourceFieldString('imprint'))
            ->hasField(new Resource\ResourceFieldFloat('default_radius'))
            ->hasField(new Resource\ResourceFieldBoolean('has_articles'))
            ->hasField(new Resource\ResourceFieldBoolean('has_brochures'))
            ->hasField(new Resource\ResourceFieldBoolean('has_coupons'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldString('datetime_modified'))
            ->hasField(new Resource\ResourceFieldString('datetime_removed'))
            ->hasField(new Resource\ResourceFieldString('external_tracking_id'))

            ->hasResource(Resource\ResourceFactory::create('industry'))
            ->hasResource(Resource\ResourceFactory::create('tags'))
            ->hasResource(Resource\ResourceFactory::create('images'));
    }

}
