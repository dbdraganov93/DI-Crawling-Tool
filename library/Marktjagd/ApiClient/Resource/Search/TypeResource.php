<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the TypeResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  search
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Search;

use Marktjagd\ApiClient\Resource;

/**
 * Type resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  search
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class TypeResource extends Resource\Resource
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
            ->hasAttribute(new Resource\ResourceAttributeInteger('offer_hits'))

            ->hasField(new Resource\ResourceFieldString('value'));
    }

}
