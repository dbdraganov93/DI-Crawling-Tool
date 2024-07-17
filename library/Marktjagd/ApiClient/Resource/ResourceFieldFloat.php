<?php

/**
 * Contains Description of ResourceFieldFloat.
 *
 * PHP version 5
 *
 * @category Resource
 * @package  Resource
 * @author   Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license  Martktjagd GmbH
 * @link     http://www.marktjagd.det
 */

namespace Marktjagd\ApiClient\Resource;

/**
 * Description of ResourceFieldFloat
 *
 * @category Resource
 * @package  Resource
 * @author   Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license  Martktjagd GmbH
 * @link     http://www.marktjagd.de
 */
class ResourceFieldFloat extends ResourceField
{

    public function __construct($name, $default = null)
    {
        parent::__construct($name, is_null($default) ? null : (float) $default);
    }

    public function setValue($value)
    {
        parent::setValue(is_null($value) ? null : (float) $value);
    }

}
