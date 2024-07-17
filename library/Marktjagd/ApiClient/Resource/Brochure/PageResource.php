<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the PageResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Robert Freigang <robert.freigang@Marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Brochure;

use Marktjagd\ApiClient\Resource;

/**
 * Page resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Robert Freigang <robert.freigang@Marktjagd.de>
 * @license     Marktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class PageResource extends Resource\Resource
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
            ->hasAttribute(new Resource\ResourceAttributeBoolean('hit'))

            ->hasField(new Resource\ResourceFieldInteger('number'))
            ->hasField(new Resource\ResourceFieldString('text'))

            ->hasResource(Resource\ResourceFactory::create('image'))
            ->hasResource(Resource\ResourceFactory::create('links'));
    }

}
