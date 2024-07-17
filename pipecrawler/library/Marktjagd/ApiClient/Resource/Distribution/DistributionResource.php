<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the DistributionResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  distribution
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Distribution;

use Marktjagd\ApiClient\Resource;
use Marktjagd\ApiClient\Request\Request;

/**
 * Distribution resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  distribution
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class DistributionResource extends Resource\Resource
{

    const
        STATUS_VISIBLE = 'visible',
        STATUS_REMOVED = 'removed';

    protected static $hasCollection = true;

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldInteger('company_id'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldBoolean('is_static'))
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldInteger('store_number'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldString('datetime_modified'))
            ->hasField(new Resource\ResourceFieldString('datetime_removed'))
            ->hasField(new Resource\ResourceFieldArrayInteger('store_ids'));
    }

    /**
     * Creates a new distribution. If a distribution with similar data already exists, this
     * distribution is loaded instead.
     *
     * @return bool TRUE on success, FALSE otherwise
     */
    public function create()
    {
        $name = static::getName();
        $primaryKey = $this->getPrimaryKey(Request::METHOD_PUT);

        $this->request = new Request($name . (empty($primaryKey) ? '' : "/$primaryKey"));
        $this->request->setRequestBody(json_encode(array($name => $this->toArray())));

        if (!$this->request->put() || !(
                $this->request->isResponseStatusCodeCreated() ||
                $this->request->isResponseStatusCodeOk()
            ))
        {
            return false;
        }

        $this->load($this->request->getResponse()->$name);
        $this->isNew = false;
        $this->isModified = false;

        // logging
        if ($this->request->isResponseStatusCodeCreated())
        {
            $this->log(null, $this);
        }

        return true;
    }

}
