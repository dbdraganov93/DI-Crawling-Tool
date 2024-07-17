<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the MallCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  mall
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Mall;

use Marktjagd\ApiClient\Resource;

/**
 * Mall collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  mall
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class MallCollectionResource extends Resource\CollectionResource
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
