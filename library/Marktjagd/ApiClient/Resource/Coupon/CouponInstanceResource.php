<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the CouponInstanceResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  coupon
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Coupon;

use Marktjagd\ApiClient\Resource;

/**
 * Coupon instance resource
 *
 * @category    resource
 * @package     resource
 * @subpackage  coupon
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 *
 * @method int getId() Returns the resource field id
 * @method string getPartner() Returns the resource field partner
 * @method int getCompanyId() Returns the resource field company_id
 * @method int getCouponId() Returns the resource field coupon_id
 * @method string getNumber() Returns the resource field number
 * @method string getStatus() Returns the resource field status
 * @method string getRedemptionUrl() Returns the resource field redemption_url
 * @method string getDatetimeDistributed() Returns the resource field datetime_distributed
 * @method string getDatetimeRedeemed() Returns the resource field datetime_redeemed
 *
 * @method \Marktjagd\ApiClient\Resource\Coupon\CouponResource setCouponId(int $couponId) Sets the resource field coupon_id
 * @method \Marktjagd\ApiClient\Resource\Coupon\CouponResource setStatus(string $status) Sets the resource field status
 */
class CouponInstanceResource extends Resource\Resource
{

    const
        STATUS_DISTRIBUTED = 'distributed',
        STATUS_REDEEMED = 'redeemed';

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
            ->hasField(new Resource\ResourceFieldString('partner'))
            ->hasField(new Resource\ResourceFieldInteger('company_id'))
            ->hasField(new Resource\ResourceFieldInteger('coupon_id'))
            ->hasField(new Resource\ResourceFieldString('number'))
            ->hasField(new Resource\ResourceFieldString('status'))
            ->hasField(new Resource\ResourceFieldString('redemption_url'))
            ->hasField(new Resource\ResourceFieldString('datetime_distributed'))
            ->hasField(new Resource\ResourceFieldString('datetime_redeemed'));
    }

}
