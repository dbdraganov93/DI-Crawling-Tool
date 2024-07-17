<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the ArticleCollectionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  article
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Article;

use Marktjagd\ApiClient\Resource;

/**
 * Article collection resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  article
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class ArticleCollectionResource extends Resource\CollectionResource
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
