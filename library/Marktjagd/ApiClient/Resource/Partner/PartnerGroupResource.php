<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the PartnerGroupResource class.
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
 * Partner group resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  partner
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 *
 * @method int getId() Returns the partner group ID
 */
class PartnerGroupResource extends Resource\Resource
{

    protected static $hasCollection = true;

    /**
     * Sets the resource definition
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldInteger('id'), true)
            ->hasField(new Resource\ResourceFieldString('title'))
            ->hasField(new Resource\ResourceFieldString('datetime_created'))
            ->hasField(new Resource\ResourceFieldArrayInteger('partner_ids'));
    }

}
