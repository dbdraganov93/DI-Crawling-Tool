<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the StoreCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  store
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Store;

use Marktjagd\ApiClient\Resource;

/**
 * Store collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  store
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class StoreCollectionResource extends Resource\CollectionResource
{

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this->hasAttribute(new Resource\ResourceAttributeInteger('hits'));
    }

}
