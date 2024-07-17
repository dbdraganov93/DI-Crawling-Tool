<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the CountyCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  geo
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Geo;

use Marktjagd\ApiClient\Resource;

/**
 * County collection resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  geo
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class CountyCollectionResource extends Resource\CollectionResource
{

    /**
     * Returns the name of the county collection resource.
     *
     * @return string resource name
     */
    protected static function getName()
    {
        return 'counties';
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
