<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the RatingResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  rating
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Rating;

use Marktjagd\ApiClient\Resource;
use Marktjagd\ApiClient\Request\Request;

/**
 * Rating resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  rating
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class RatingResource extends Resource\Resource
{

    const
        TYPE_ARTICLE = 'article',
        TYPE_BROCHURE = 'brochure',
        TYPE_COUPON = 'coupon',
        TYPE_STORE = 'store';

    /**
     * Load a rating resource object.
     *
     * @param string $type      rating type (article, brochure, store)
     * @param int    $typeId    rating type id
     * @param string $visitorId visitor id
     * @param array  $params    request parameters
     * @return RatingResource the resource object or NULL if the request failed
     */
    public static function find($type, $typeId, $visitorId = null, array $params = array())
    {
        $primaryKey = "$type/$typeId";

        if (!is_null($visitorId))
        {
            $primaryKey .= "/$visitorId";
        }

        return parent::doFind($primaryKey, $params);
    }

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldString('type'), true, Request::METHOD_ALL)
            ->hasField(new Resource\ResourceFieldInteger('type_id'), true, Request::METHOD_ALL)
            ->hasField(new Resource\ResourceFieldInteger('mean_value'))
            ->hasField(new Resource\ResourceFieldInteger('range'))
            ->hasField(new Resource\ResourceFieldInteger('number'))
            ->hasField(new Resource\ResourceFieldString('visitor_id'), true, Request::METHOD_ALL ^ Request::METHOD_GET)
            ->hasField(new Resource\ResourceFieldInteger('value'))
            ->hasField(new Resource\ResourceFieldString('datetime_from'));
    }

}
