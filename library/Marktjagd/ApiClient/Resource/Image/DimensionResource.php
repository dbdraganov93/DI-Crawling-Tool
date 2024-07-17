<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the DimensionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  image
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Image;

use Marktjagd\ApiClient\Resource;

/**
 * Dimension resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  image
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class DimensionResource extends Resource\Resource
{

    protected static $hasCollection = true;

    /**
     * Sets the resource definition
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldString('url'))
            ->hasField(new Resource\ResourceFieldInteger('width'))
            ->hasField(new Resource\ResourceFieldInteger('height'));
    }

}
