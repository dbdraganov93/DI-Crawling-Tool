<?php
/**
 * Contains Description of ResourceAttributeFloat.
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
 * Description of ResourceAttributeFloat
 *
 * @category Resource
 * @package  Resource
 * @author   Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license  Martktjagd GmbH
 * @link     http://www.marktjagd.de
 */
class ResourceAttributeFloat extends ResourceAttribute
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
