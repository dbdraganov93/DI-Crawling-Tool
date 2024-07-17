<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the PageCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Brochure;

use Marktjagd\ApiClient\Resource;

/**
 * Page collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  brochure
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class PageCollectionResource extends Resource\CollectionResource
{

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this->hasAttribute(new Resource\ResourceAttributeInteger('num'));
        $this->hasAttribute(new Resource\ResourceAttributeInteger('hits'));
    }

}
