<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the CompanyProductResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  product
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Product;

use Marktjagd\ApiClient\Resource;
use Marktjagd\ApiClient\Request\Request;

/**
 * CompanyProduct resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  product
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Marktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class CompanyProductResource extends Resource\Resource
{

    const
        STATUS_CURRENT = 'current',
        STATUS_PREVIOUS = 'previous',
        STATUS_NEXT = 'next',
        STATUS_RECALLED = 'recalled';

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
            ->hasField(new Resource\ResourceFieldInteger('company_id'))
            ->hasField(new Resource\ResourceFieldInteger('product_id'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldString('datetime_from'))
            ->hasField(new Resource\ResourceFieldString('datetime_to'))
            ->hasField(new Resource\ResourceFieldString('datetime_booked'))
            ->hasField(new Resource\ResourceFieldString('datetime_cancelled'))
            ->hasField(new Resource\ResourceFieldBoolean('cancelled'))

            ->hasResource(Resource\ResourceFactory::create('product'));
    }

}
