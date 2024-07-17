<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the ImportDefinitionExampleResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  import
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Import;

use Marktjagd\ApiClient\Resource;

/**
 * Import definition example resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  import
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class ImportDefinitionExampleResource extends Resource\Resource
{

    protected static $hasCollection = true;

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this->hasField(new Resource\ResourceFieldString('value'));
    }

}
