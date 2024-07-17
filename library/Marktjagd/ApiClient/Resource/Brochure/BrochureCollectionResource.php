<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the BrochureCollectionResource class.
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
 * Brochure collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class BrochureCollectionResource extends Resource\CollectionResource
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
