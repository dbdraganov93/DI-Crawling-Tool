<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the PriceCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     search
 * @subpackage  price
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Search;

use Marktjagd\ApiClient\Resource;

/**
 * Price collection resource.
 *
 * @category    resource
 * @package     search
 * @subpackage  price
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class PriceCollectionResource extends Resource\CollectionResource
{

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasAttribute(new Resource\ResourceAttributeFloat('min'))
            ->hasAttribute(new Resource\ResourceAttributeFloat('max'));
    }

}
