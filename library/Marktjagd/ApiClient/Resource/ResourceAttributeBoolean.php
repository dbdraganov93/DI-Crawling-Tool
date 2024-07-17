<?php
/**
 * Contains Description of ResourceAttributeBoolean.
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
 * Description of ResourceAttributeBoolean
 *
 * @category Resource
 * @package  Resource
 * @author   Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license  Martktjagd GmbH
 * @link     http://www.marktjagd.de
 */
class ResourceAttributeBoolean extends ResourceAttribute
{
    public function __construct($name, $default = null)
    {
        parent::__construct($name, is_null($default) ? null : (bool) $default);
    }

    public function setValue($value)
    {
        parent::setValue(is_null($value) ? null : (bool) $value);
    }
}
