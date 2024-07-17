<?php

/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the TrackingResource class.
 *
 * PHP version 5
 *
 * @category    resource
 * @package     resource
 * @subpackage  tracking
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Resource\Tracking;

use Marktjagd\ApiClient\Resource;
use Marktjagd\ApiClient\Request\Request;

/**
 * Tracking resource.
 *
 * @category    resource
 * @package     resource
 * @subpackage  tracking
 * @author      Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license     Martktjagd GmbH
 * @link        http://www.marktjagd.de
 * 
 * @method ScreenResource getScreen() Returns the resource field screen.
 */
class TrackingResource extends Resource\Resource
{

    const
        TYPE_ARTICLE = 'article',
        TYPE_BROCHURE = 'brochure',
        TYPE_BROCHURE_PAGE = 'brochure_page',
        TYPE_COUPON = 'coupon',
        TYPE_CATALOG = 'catalog',
        TYPE_COMPANY = 'company',
        TYPE_MALL = 'mall',
        TYPE_STORE = 'store',
        ACTION_LIST = 'list',
        ACTION_DETAIL = 'detail',
        ACTION_CLICKOUT = 'clickout',
        ACTION_INQUIRY = 'inquiry',
        ACTION_DISTRIBUTION = 'distribution',
        ACTION_REDEMPTION = 'redemption';

    protected static $hasCollection = true;
    
    protected static $loggingEnabled = false;


    /**
     * Load a tracking resource object.
     *
     * @param string $type      tracking type (article, brochure, store)
     * @param int    $typeId    tracking type id
     * @param int    $page      brochure page
     * @param array  $params    request parameters
     * @return TrackingResource the resource object or NULL if the request failed
     */
    public static function find($type, $typeId, $page = null, array $params = array())
    {
        $primaryKey = $type . '/' . $typeId;

        if (!is_null($page))
        {
            $primaryKey .= '/' . $page;
        }

        return parent::doFind($primaryKey, $params);
    }

    /**
     * Sets the resource definition.
     *
     * @return void
     */
    protected function setResourceDefinition()
    {
        $this
            ->hasField(new Resource\ResourceFieldInteger('id'), true, Request::METHOD_POST)
            ->hasField(new Resource\ResourceFieldInteger('partner_id'))
            ->hasField(new Resource\ResourceFieldInteger('company_id'))
            ->hasField(new Resource\ResourceFieldString('type'), true, Request::METHOD_ALL ^ Request::METHOD_DELETE)
            ->hasField(new Resource\ResourceFieldInteger('type_id'), true, Request::METHOD_ALL ^ Request::METHOD_DELETE)
            ->hasField(new Resource\ResourceFieldInteger('page'))
            ->hasField(new Resource\ResourceFieldString('action'))
            ->hasField(new Resource\ResourceFieldString('date'))
            ->hasField(new Resource\ResourceFieldInteger('number'))
            ->hasField(new Resource\ResourceFieldString('visitor_id'))
            ->hasField(new Resource\ResourceFieldFloat('longitude'))
            ->hasField(new Resource\ResourceFieldFloat('latitude'))
            ->hasField(new Resource\ResourceFieldString('source'))
            ->hasField(new Resource\ResourceFieldString('client'))
            ->hasField(new Resource\ResourceFieldInteger('variant'))
            ->hasField(new Resource\ResourceFieldString('referrer'))
            ->hasField(new Resource\ResourceFieldInteger('duration'))
            
            ->hasResource(Resource\ResourceFactory::create('screen'));
    }
    
    /**
     * Sets the embedded screen resource.
     * 
     * @param ScreenResource $screen The screen resource.
     * @return TrackingResource The current instance.
     */
    public function setScreen(ScreenResource $screen)
    {
        $this->resources['screen'] = $screen;
        $this->isModified = true;
        
        return $this;
    }
    
    /**
     * Load the resource fields from an assoziative array.
     * 
     * @param array $values The resource field values.
     * @return TrackingResource The current instance.
     */
    public function fromArray(array $values = array())
    {
        parent::fromArray($values);
        
        if (isset($values['screen']))
        {
            $screen = Resource\ResourceFactory::create('screen');
            $screen->fromArray($values['screen']);
            $this->setScreen($screen);
        }
        
        return $this;
    }
    
    /**
     * Returns an assozative array with the values of the resource field.
     * 
     * @return array resource field values
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['screen'] = $this->resources['screen']->toArray();
        
        return $array;
    }
}
