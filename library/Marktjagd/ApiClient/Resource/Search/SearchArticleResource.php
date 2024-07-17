<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the SearchArticleResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  search
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Search;

use Marktjagd\ApiClient\Resource;

/**
 * SearchArticle resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  search
 * @author      Robert Freigang <robert.freigang@marktjagd.de>
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class SearchArticleResource extends Resource\Resource
{

    /**
     * Load a search article resource object.
     *
     * @param array  $params    request parameters
     * @return SearchArticleResource the resource object or NULL if the request failed
     */
    public static function find(array $params = array())
    {
        return parent::doFind(null, $params);
    }

    /**
     * Sets the resource definition
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasResource(Resource\ResourceFactory::create('articles'))
            ->hasResource(Resource\ResourceFactory::create('companies'))
            ->hasResource(Resource\ResourceFactory::create('time_constraints'))
            ->hasResource(Resource\ResourceFactory::create('prices'))
            ->hasResource(Resource\ResourceFactory::create('industries'))
            ->hasResource(Resource\ResourceFactory::create('categories'))
            ->hasResource(Resource\ResourceFactory::create('malls'));
    }

}
