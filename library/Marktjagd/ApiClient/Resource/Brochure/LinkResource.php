<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the LinkResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Brochure;

use Marktjagd\ApiClient\Resource;

/**
 * Link resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Marktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class LinkResource extends Resource\Resource
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
            ->hasField(new Resource\ResourceFieldString('url'))
            ->hasField(new Resource\ResourceFieldFloat('left'))
            ->hasField(new Resource\ResourceFieldFloat('top'))
            ->hasField(new Resource\ResourceFieldFloat('width'))
            ->hasField(new Resource\ResourceFieldFloat('height'));
    }

}
