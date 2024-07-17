<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the ArticleResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  alert
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Alert;

use Marktjagd\ApiClient\Resource;

/**
 * Alert resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  alert
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class AlertResource extends Resource\Resource
{
    
    const
        TYPE_ARTICLE = 'article',
        TYPE_BROCHURE = 'brochure',
        TYPE_COUPON = 'coupon',
    
        STATUS_CREATED = 'created',
        STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation',
        STATUS_ACTIVE = 'active',
        STATUS_DISABLED = 'disabled',
        STATUS_REMOVED = 'removed';

    protected static $hasCollection = true;

    /**
     * Sets the resource definition
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasAttribute(new Resource\ResourceAttributeBoolean('favored'))

            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldString('partner'))
            ->hasField(new Resource\ResourceFieldString('email'))
            ->hasField(new Resource\ResourceFieldFloat('longitude'))
            ->hasField(new Resource\ResourceFieldFloat('latitude'))
            ->hasField(new Resource\ResourceFieldArrayInteger('filter_company_ids'))
            ->hasField(new Resource\ResourceFieldArrayInteger('filter_industry_ids'))
            ->hasField(new Resource\ResourceFieldArray('filter_searches'))
            ->hasField(new Resource\ResourceFieldArray('filter_brands'))
            ->hasField(new Resource\ResourceFieldArray('filter_types'))
            ->hasField(new Resource\ResourceFieldArray('filter_hypernyms'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldString('datetime_modified'))
            ->hasField(new Resource\ResourceFieldString('datetime_removed'));
    }

}
