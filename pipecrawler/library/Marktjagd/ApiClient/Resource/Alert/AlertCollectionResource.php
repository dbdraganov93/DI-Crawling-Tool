<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the AlertCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  alert
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Alert;

use Marktjagd\ApiClient\Resource;

/**
 * Alert collection resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  alert
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class AlertCollectionResource extends Resource\CollectionResource
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
