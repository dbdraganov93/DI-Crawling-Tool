<?php
/**
 * Marktjagd RESTful API request.
 *
 * @author Lutz Petzoldt
 */
class MjRestRequest extends RestRequest
{
    private static
        $host,
        $keyId,
        $secretKey
    ;

    private
        $resource,
        $params
    ;

    /**
     * Constructor.
     *
     * @param string $resource resource
     * @param array $params request parameters
     */
    public function __construct($resource, array $params = null)
    {
        $this->setFormat(self::FORMAT_JSON);
        $this->setResource($resource);
        $this->params = $params;
    }

    /**
     * Set the api host.
     *
     * @param string $host api host
     */
    public static function setHost($host)
    {
        self::$host = $host;
    }

    /**
     * Set the api key id.
     *
     * @param string $keyId api key id
     */
    public static function setKeyId($keyId)
    {
        self::$keyId = $keyId;
    }

    /**
     * Set the api secret key.
     *
     * @param string $secretKey api secret key
     */
    public static function setSecretKey($secretKey)
    {
        self::$secretKey = $secretKey;
    }

    /**
     * Set the requested resource.
     *
     * @param string $resource resource string
     * @return MjRestRequest $this
     */
    protected function setResource($resource)
    {
        if (!preg_match('|[a-z]+(/[a-z0-9]+){0,3}|', $resource))
        {
            throw new InvalidArgumentException(__METHOD__ . ': Malformed resource');
        }

        $this->resource = $resource;
    }

    /**
     * Set a request parameter.
     *
     * @param string $name parameter name
     * @param string $value parameter value
     * @return RestRequest $this
     */
    public function setParam($name, $value)
    {
        if (is_null($this->params))
        {
            $this->params = array();
        }

        $this->params[$name] = $value;

        return $this;
    }

    /**
     * Set the request parameters.
     *
     * @param array $params associative array with parameters
     * @return RestRequest $this
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Execute the api request.
     *
     * @return bool TRUE if the request was successful, FALSE otherwise
     */
    protected function execute()
    {
        // build signature
        $date = date('r');
        $sigStr = "$this->method/$this->resource.$this->format$date" . self::$secretKey;
        $signature = base64_encode(sha1($sigStr));

        $this
            ->setRequestHeader('Date', $date)
            ->setRequestHeader('auth', self::$keyId . ":$signature");

        // build url
        $url = self::$host . "/$this->resource.json";

        if (!empty($this->params))
        {
            $url .= '?' . http_build_query($this->params, null, '&');
        }

        $this->setUrl($url);

        return parent::execute();
    }
}

/**
 * Generic RESTful API request.
 *
 * @author Lutz Petzoldt
 */
class RestRequest
{
    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';
    const METHOD_PUT    = 'PUT';
    const METHOD_DELETE = 'DELETE';

    const FORMAT_JSON   = 'json';
    const FORMAT_XML    = 'xml';

    const CONTENT_TYPE_JSON = 'application/jsonrequest';
    const CONTENT_TYPE_XML  = 'text/xml';

    const STATUS_CODE_OK        = 200;
    const STATUS_CODE_CREATED   = 201;

    protected static
        $contentTypes = array(
        self::FORMAT_JSON => self::CONTENT_TYPE_JSON,
        self::FORMAT_XML => self::CONTENT_TYPE_XML
    );

    protected
        $url,
        $verifyPeer = true,
        $method,
        $format,
        $requestBody,
        $requestContentType,
        $requestHeaders,
        $requestOptions,
        $responseBody,
        $responseInfo;


    /**
     * Constructor.
     *
     * @param string $url request URL
     * @param string $format response format
     */
    public function __construct($url, $format = self::FORMAT_JSON)
    {
        $this
            ->setUrl($url)
            ->setFormat($format);
    }

    /**
     * Initiate a GET request.
     *
     * @return bool TRUE if the request was successful, FALSE otherwise
     */
    public function get()
    {
        $this->method = self::METHOD_GET;

        return $this->execute();
    }

    /**
     * Initiate a POST request.
     *
     * @return bool TRUE if the request was successful, FALSE otherwise
     */
    public function post()
    {
        $this->method = self::METHOD_POST;
        $this
            ->setRequestOption(CURLOPT_POST, true)
            ->setRequestOption(CURLOPT_POSTFIELDS, $this->requestBody)
            ->setRequestHeader('Content-Type', $this->requestContentType);

        return $this->execute();
    }

    /**
     * Initiate a PUT request.
     *
     * @return bool TRUE if the request was successful, FALSE otherwise
     */
    public function put()
    {
        $this->method = self::METHOD_PUT;

        $fh = fopen('php://memory', 'rw');
        fwrite($fh, $this->requestBody);
        rewind($fh);

        $this
            ->setRequestOption(CURLOPT_PUT, true)
            ->setRequestOption(CURLOPT_INFILE, $fh)
            ->setRequestOption(CURLOPT_INFILESIZE, strlen($this->requestBody))
            ->setRequestHeader('Content-Type', $this->requestContentType);

        return $this->execute();
    }

    /**
     * Initiate a DELETE request.
     *
     * @return bool TRUE if the request was successful, FALSE otherwise
     */
    public function delete()
    {
        $this->method = self::METHOD_DELETE;
        $this->setRequestOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $this->execute();
    }

    /**
     * Set the URL.
     *
     * @param string $url URL
     * @return RestRequest $this
     */
    protected function setUrl($url)
    {
        if (empty($url) || false === parse_url($url))
        {
            throw new InvalidArgumentException(__METHOD__ . ": Malformed URL ($url)");
        }

        $this->url = $url;

        return $this;
    }

    /**
     * Set the response format.
     *
     * @param string $format response format
     * @return RestRequest $this
     */
    protected function setFormat($format)
    {
        if (!in_array($format, array(self::FORMAT_JSON, self::FORMAT_XML)))
        {
            throw new UnexpectedValueException(__METHOD__ . ': Invalid format');
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Set the request body for POST or PUT requests
     *
     * @param string $content request body string
     * @param string $contentType request body content type
     * @return RestRequest $this
     */
    public function setRequestBody($requestBody, $contentType = null)
    {
        $this->requestBody = (string) $requestBody;
        $this->requestContentType = is_null($contentType) ? self::$contentTypes[$this->format] : $contentType;

        return $this;
    }

    /**
     * Set a request http header.
     *
     * @param string $name header name
     * @param string $value header value
     * @return RestRequest $this
     */
    public function setRequestHeader($name, $value)
    {
        if (is_null($this->requestHeaders))
        {
            $this->requestHeaders = array();
        }

        $this->requestHeaders[$name] = $value;

        return $this;
    }

    /**
     * Set a curl option.
     *
     * @param string $name option name
     * @param mixed $value option value
     * @return RestRequest $this
     * @see curl_setopt
     */
    protected function setRequestOption($name, $value)
    {
        if (is_null($this->requestOptions))
        {
            $this->requestOptions = array();
        }

        $this->requestOptions[$name] = $value;

        return $this;
    }

    /**
     * Execute the api request.
     *
     * @return bool TRUE if the request was successful, FALSE otherwise
     */
    protected function execute()
    {
        if (!is_null($this->requestBody))
        {
        }

        $curl = curl_init();

        // build curl headers
        $this->setRequestHeader('Accept', self::$contentTypes[$this->format]);
        $headers = array();

        foreach ($this->requestHeaders as $key => $value)
        {
            $headers[] = "$key: $value";
        }

        // set curl options
        $this
            ->setRequestOption(CURLOPT_URL, $this->url)
            ->setRequestOption(CURLOPT_TIMEOUT, 60)
            ->setRequestOption(CURLOPT_SSL_VERIFYPEER, $this->verifyPeer)
            ->setRequestOption(CURLOPT_HEADER, false)
            ->setRequestOption(CURLOPT_RETURNTRANSFER, true)
            ->setRequestOption(CURLOPT_HTTPHEADER, $headers);
        curl_setopt_array($curl, $this->requestOptions);

        $tries = 10;
        $sleep = 1;

        do
        {
            $this->responseBody = curl_exec($curl);
        }
        while (false === $this->responseBody && --$tries && 0 === sleep($sleep++));

        $this->responseInfo = curl_getinfo($curl);

        curl_close($curl);

        return false !== $this->responseBody;
    }

    /**
     * Get the raw response body.
     *
     * @return string response body
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * Get information about the request.
     *
     * @param string $name specific information title
     * @return string|array information value if $name is given, information array otherwise
     * @see curl_getinfo
     */
    public function getResponseInfo($name = null)
    {
        if (!is_null($name))
        {
            return $this->responseInfo[$name];
        }

        return $this->responseInfo;
    }

    /**
     * Get response status code.
     *
     * @return int status code
     */
    public function getResponseStatusCode()
    {
        return (int) $this->getResponseInfo('http_code');
    }

    /**
     * Check if the response status code equals 200
     *
     * @return bool TRUE if response status code equals 200, FALSE otherwise
     */
    public function isResponseStatusCodeOk()
    {
        return $this->getResponseStatusCode() == self::STATUS_CODE_OK;
    }

    /**
     * Check if the response status code equals 201
     *
     * @return bool TRUE if response status code equals 201, FALSE otherwise
     */
    public function isResponseStatusCodeCreated()
    {
        return $this->getResponseStatusCode() == self::STATUS_CODE_CREATED;
    }

    /**
     * Get the response as object.
     *
     * @return stdClass|SimpleXMLElement response object
     */
    public function getResponse()
    {
        switch ($this->format)
        {
            case self::FORMAT_JSON:
                $response = json_decode($this->responseBody);
                break;

            case self::FORMAT_XML:
                $response = new SimpleXMLElement($this->responseBody, LIBXML_NOCDATA);
                break;
        }

        return $response;
    }
}
