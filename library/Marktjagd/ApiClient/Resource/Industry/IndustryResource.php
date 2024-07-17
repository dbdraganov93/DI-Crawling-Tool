<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the IndustryResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  industry
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Industry;

use Marktjagd\ApiClient\Resource;

/**
 * Industry resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  industry
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class IndustryResource extends Resource\Resource
{

    protected static $hasCollection = true;

    /**
     * Sets the resource definition.
     *
     * Parent and children resources are not implemented, because this would cause
     * an infinite recursion.
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
            ->hasField(new Resource\ResourceFieldInteger('parent_id'))
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldFloat('default_radius'));
    }

}
