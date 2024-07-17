<?php

namespace Marktjagd\ApiClient\Request;

/**
 * RequestLogger
 *
 * @author Lutz Petzoldt <lutz.petzoldt@marktjagd.de>
 */
abstract class RequestLogger
{
    const
        LOG_LEVEL_ERROR = 0,
        LOG_LEVEL_INFO  = 1;

    protected static
        $logLevels = array(
            self::LOG_LEVEL_ERROR => 'error',
            self::LOG_LEVEL_INFO  => 'info'
        );

    private
        $logLevel = self::LOG_LEVEL_INFO,
        $options  = array();

    /**
     * Returns the priority name given a priority class constant.
     *
     * @staticvar array $priorities priority names
     * @param int $priority priority class constant
     * @return string priority name
     */
    protected static function getLogLevelName($logLevel)
    {
        if (!isset(self::$logLevels[$logLevel]))
        {
            throw new \UnexpectedValueException(sprintf('Undefined log level %s', $logLevel));
        }

        return self::$logLevels[$logLevel];
    }

    /**
     * Checks whether the given string contains binary data.
     *
     * @param string $string
     * @param int $testLength
     * @return bool TRUE if the string contains binary data, FALSE otherwise
     */
    protected static function isBinary($string, $testLength = 256)
    {
        $testString = substr($string, 0, $testLength);
        $testString = str_replace(array(chr(10), chr(13)), '', $testString);

        return !ctype_print($testString);
    }

    /**
     * Initializes the current request logger. Override this method to use your
     * own initialization.
     */
    abstract protected function initialize();

    /**
     * Logs a request.
     *
     * @param Request $request  request
     * @param int     $logLevel log level class constant
     * @return bool TRUE if the request has been logged successfully, FALSE otherwise
     */
    abstract protected function doLog(Request $request, $logLevel);

    public function __construct(array $options = array())
    {
        if (isset($options['log_level']))
        {
            $this->setLogLevel($options['log_level']);
            unset($options['log_level']);
        }

        $this->setOptions($options);
        $this->initialize();
    }

    /**
     * Set the log level.
     *
     * @param int|string $logLevel log level as class contant or string
     * @return RequestLogger current instance
     */
    public function setLogLevel($logLevel)
    {
        if (is_int($logLevel))
        {
            if (!isset(self::$logLevels[$logLevel]))
            {
                throw new \UnexpectedValueException(sprintf('Undefined log level %s', $logLevel));
            }

            $this->logLevel = $logLevel;
        }
        else
        {
            if (!in_array($logLevel, self::$logLevels))
            {
                throw new \UnexpectedValueException(sprintf('Undefined log level %s', $logLevel));
            }

            $this->logLevel = array_search($logLevel, self::$logLevels);
        }

        return $this;
    }

    /**
     * Returns the current log level.
     *
     * @return int log level
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * Sets the options. Existing options will be replaced.
     *
     * @param array $options options array
     * @return \Marktjagd\ApiClient\Request\RequestLogger current instance
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Sets the value of an option. If the option is already set, this will
     * override the current value.
     *
     * @param string $name option name
     * @param string $value option value
     * @return \Marktjagd\ApiClient\Request\RequestLogger current instance
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * Checks if an options isset set and not NULL.
     *
     * @param string $name option name
     * @return bool TRUE if the options is set and not NULL, FALSE otherwise
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * Returns the option value or the default value if the option is not set.
     *
     * @param string $name option name
     * @param mixed $default default value
     * @return mixed option value or default value
     */
    public function getOption($name, $default = null)
    {
        return $this->hasOption($name) ? $this->options[$name] : $default;
    }

    /**
     * Logs a request if it has the appropriate priority.
     *
     * @param Request $request request
     * @return bool TRUE if the request has been logged, FALSE otherwise
     */
    public function log(Request $request)
    {
        if ($request->isResponseStatusCodeOk() || $request->isResponseStatusCodeCreated())
        {
            $logLevel = self::LOG_LEVEL_INFO;
        }
        else
        {
            $logLevel = self::LOG_LEVEL_ERROR;
        }

        if ($this->getLogLevel() < $logLevel)
        {
            return false;
        }

        return $this->doLog($request, $logLevel);
    }

}
