<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the PartnerCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  partner
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Partner;

use Marktjagd\ApiClient\Resource;

/**
 * Partner collection resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  partner
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class PartnerCollectionResource extends Resource\CollectionResource
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
