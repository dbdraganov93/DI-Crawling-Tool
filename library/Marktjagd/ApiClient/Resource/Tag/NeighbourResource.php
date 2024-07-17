<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the NeighbourResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  tag
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Tag;

use Marktjagd\ApiClient\Resource;

/**
 * Neighbour resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  tag
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class NeighbourResource extends Resource\Resource
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
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldFloat('score'));
    }

}
