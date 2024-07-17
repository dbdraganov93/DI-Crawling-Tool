<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the IndustryCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  industry
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Industry;

use Marktjagd\ApiClient\Resource;

/**
 * Industry collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  industry
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class IndustryCollectionResource extends Resource\CollectionResource
{

    /**
     * Returns the name of the industry collection resource.
     *
     * @return string resource name
     */
    protected static function getName()
    {
        return 'industries';
    }

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
