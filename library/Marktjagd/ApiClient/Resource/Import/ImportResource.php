<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the ImportResource class.
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
 * Import resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  import
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */
class ImportResource extends Resource\Resource
{

    const
        TYPE_ARTICLE = 'article',
        TYPE_STORE = 'store',
        TYPE_BROCHURE = 'brochure',
        STATUS_NEW = 'new',
        STATUS_IMPORTING = 'importing',
        STATUS_DONE = 'done',
        STATUS_ERROR = 'error',
        STATUS_SKIPPED = 'skipped',
        STATUS_SUSPENDED = 'suspended',
        STATUS_CRONJOB = 'cronjob';

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
            ->hasField(new Resource\ResourceFieldString('type'))
            ->hasField(new Resource\ResourceFieldString('source'))
            ->hasField(new Resource\ResourceFieldString('extension'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldString('converter'))
            ->hasField(new Resource\ResourceFieldString('url'))
            ->hasField(new Resource\ResourceFieldString('cron'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldString('datetime_last_changed'))
            ->hasField(new Resource\ResourceFieldString('datetime_started'))
            ->hasField(new Resource\ResourceFieldString('catalog_type'))
            ->hasField(new Resource\ResourceFieldString('catalog_type_id'))
            ->hasField(new Resource\ResourceFieldString('upload_file'))
            ->hasField(new Resource\ResourceFieldString('upload_file_extension'))
            ->hasField(new Resource\ResourceFieldBoolean('may_skip'))
            ->hasField(new Resource\ResourceFieldBoolean('online'))
            ->hasField(new Resource\ResourceFieldString('behavior'))

            ->hasResource(Resource\ResourceFactory::create('import_errors'))
            ->hasResource(Resource\ResourceFactory::create('import_warnings'));
    }

}
