<?php
/**
 * This file is part of the Marktjagd RESTful API Client and
 * contains the RequestError class.
 *
 * PHP version 5
 *
 * @category request
 * @package  request
 * @author   Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license  Martktjagd GmbH
 * @link     http://www.marktjagd.de
 */

namespace Marktjagd\ApiClient\Request;

/**
 * Request error
 *
 * @category request
 * @package  request
 * @author   Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 * @license  Martktjagd GmbH
 * @link     http://www.marktjagd.de
 */
class RequestError
{

    const
        TYPE_CURL       = 'curl',
        TYPE_INVALID    = 'invalid',
        TYPE_REQUIRED   = 'required',
        TYPE_DUPLICATE  = 'duplicate',
        TYPE_ADDRESS    = 'address',
        TYPE_PRIORITY   = 'priority',
        TYPE_LIMIT      = 'limit',
        TYPE_GENERAL    = 'general'
    ;

    protected
        $type,
        $arguments;


    /**
     * Constructor.
     *
     * @param string $type      error type
     * @param array  $arguments array of named arguments needed to render the
     *                          error message
     */
    public function __construct($type, array $arguments = array())
    {
        $this->type      = $type;
        $this->arguments = $arguments;
    }

    /**
     * Returns the error type.
     *
     * @return string error type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the error arguments.
     *
     * @return array error arguments
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
