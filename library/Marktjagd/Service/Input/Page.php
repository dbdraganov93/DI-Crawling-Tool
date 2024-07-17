<?php

/**
 * Service-Klasse, die sich um das Abrufen/Crawlen einer Webseite kümmert
 *
 * Class Marktjagd_Service_Input_Page
 */
class Marktjagd_Service_Input_Page
{
    /* @var Marktjagd_Entity_Page */

    protected $page;
    protected $_logger;

    /**
     * Konstruktor
     */
    public function __construct($rawMode = false)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->page = new Marktjagd_Entity_Page($rawMode);
    }

    /**
     * Öffnet eine HTML Seite
     *
     * @param string $url
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function open($url, $params = array())
    {
        $page = $this->page;

        // Wartezeit für den Crawler
//        usleep(200 * 1000);

        if ('' == trim($url)) {
            $page->setErrorString('missing paramater: $url');
            return false;
        }

        $client = $page->getClient();
        $client->setUri(($url));
        $client->setConfig(array(
            'maxredirects' => $page->getLoadTries(),
            'timeout' => $page->getTimeout(),
            'useragent' => $page->getUserAgent()
        ));

        if ($page->getMethod() == 'GET') {
            $client->setParameterGet($params);
        } elseif ($page->getMethod() == 'POST') {
            $client->setParameterPost($params);
        } else {
            $page->setErrorString('method ' . $page->getMethod() . ' is not allowed');
            return false;
        }

        //As mentioned by Client, bypass the robots.txt rules
        //only for customer "DecathlonFr"

        if ($url == 'https://www.decathlon.fr/store-locator' || $url == 'https://www.mfo-matratzen.de/service/filialfinder') {
            $page->setIgnoreRobots(TRUE);
        }

        // Prüft auf die robots.txt
        if (!$page->getIgnoreRobots()) {
            $robots = new Marktjagd_Service_Validator_RobotsTxt();
            if (!$robots->checkRobotsPermission($url)) {
                $page->setErrorString('disallowed by robots.txt');
                return false;
            }
        }

        // Prüft, ob Cookies gesetzt/benutzt werden sollen
        if ($page->getUseCookies()) {
            // Cookies in Session gespeichert?
            if (isset($_SESSION['cookiejar']) &&
                $_SESSION['cookiejar'] instanceof Zend_Http_CookieJar
            ) {

                $client->setCookieJar($_SESSION['cookiejar']);
            } else {
                // Falls nicht, speichere die Cookies
                $client->setCookieJar();
            }
        }

        // Absenden des Requests & Empfangen der Antwort
        $response = $client->request($page->getMethod());

        if ($page->getUseCookies()) {
            // Speichere die Cookies in der Session für die nächste Seite
            $_SESSION['cookiejar'] = $client->getCookieJar();
        }

        if ($page->getStoreHeaderInformations()) {
            $page->setResponseHeader($response->getHeaders());
        }

//        $this->_logger->info('opening ' . $url);

        $page->setResponseBody($this->_transformResponse($response->getBody(), $params));

        return true;
    }

    /**
     * Bereinigt/Modifiziert die Response anhand der übergebenen oder gesetzten Parameter
     * @param $response
     * @param $params
     * @return string
     */
    protected function _transformResponse($response, $params)
    {
        $sTextFormat = new Marktjagd_Service_Text_TextFormat();
        $page = $this->getPage();

        // Konvertiert Zeichensatz in UTF-8
        if ((array_key_exists('convertCharset', $params) && true === $params['convertCharset']) || (!array_key_exists('convertCharset', $params) && true === $page->getAlwaysConvertCharset())
        ) {
            $response = $sTextFormat->convertTextToUtf8($response);
        }

        // Konvertiert HTML-Entities in UTF-8 Zeichen
        if ((array_key_exists('htmlDecode', $params) && true === $params['htmlDecode']) || (!array_key_exists('htmlDecode', $params) && true === $page->getAlwaysHtmlDecode())
        ) {
            $response = $sTextFormat->htmlDecode($response);
        }

        // Entfernt alle HTML Kommentare
        if ((array_key_exists('stripComments', $params) && true === $params['stripComments']) || (!array_key_exists('stripComments', $params) && true === $page->getAlwaysStripComments())
        ) {
            $response = $sTextFormat->stripComments($response);
        }

        // Entfernt alle Zeilenumbrüche
        if ((array_key_exists('stripNewLines', $params) && true === $params['stripNewLines']) || (!array_key_exists('stripNewLines', $params) && true === $page->getAlwaysStripNewLines())
        ) {
            $response = $sTextFormat->stripNewLines($response);
        }

        // Entfernt alle Leerzeichen
        if ((array_key_exists('stripWhiteSpace', $params) && true === $params['stripWhiteSpace']) || (!array_key_exists('stripWhiteSpace', $params) && true === $page->getAlwaysStripWhiteSpace())
        ) {
            $response = $sTextFormat->stripWhiteSpace($response);
        }

        return $response;
    }

    /**
     * @param Marktjagd_Entity_Page $page
     * @return Marktjagd_Service_Input_Page
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return Marktjagd_Entity_Page
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Funktion zur Prüfung der URL-Erreichbarkeit
     *
     * @param string zu prüfende URL $url
     * @return string|NULL
     */
    public function checkUrlReachability($url, $errorCodes = '200')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if (!preg_match('#(' . $errorCodes . ')#', $info['http_code'])) {
            return NULL;
        }

        return $url;
    }


    /**
     * @param string $url
     * @param string $id
     * @return DOMElement
     * @throws Exception
     */
    public function getDomElFromUrlByID($url, $id)
    {
        $doc = $this->getResponseAsDOM($url);
        return $doc->getElementById($id);
    }

    /**
     * @param string $url
     * @param string | array $class
     * @param string $elementTag
     * @param bool $strict
     * @return array
     * @throws Exception
     */
    public function getDomElsFromUrlByClass($url, $class, $elementTag = '*', $strict = false)
    {
        if ($strict && is_array($class)) {
            Zend_Registry::get('logger')->err(__CLASS__ . '->' . __FUNCTION__ . "(): strict can´t handle more than one Property");
            return [];
        }
        return $this->getDomElsFromUrl($url, $class, 'class', $elementTag, $strict);
    }

    /**
     * @param string $url
     * @param string | array $prop
     * @param string $propName
     * @param string $tag
     * @param bool $strict
     * @return array
     * @throws Exception
     */
    public function getDomElsFromUrl($url, $prop, $propName = 'class', $tag = '*', $strict = false)
    {
        if ($strict && is_array($prop)) {
            Zend_Registry::get('logger')->err(__CLASS__ . '->' . __FUNCTION__ . "(): strict can´t handle more than one Property");
            return [];
        }
        $doc = $this->getResponseAsDOM($url);
        return $this->findDomElements($doc, $prop, $propName, $tag, $strict);
    }

    /**
     * @param DOMElement | DOMDocument $domElements
     * @param string $prop
     * @param string $propName
     * @param string $tag
     * @param bool $strict
     * @return array
     * @throws Zend_Exception
     */
    public function getDomElsFromDomEl($domElements, $prop, $propName = 'class', $tag = '*', $strict = false)
    {
        if (!$domElements || ($strict && is_array($prop))) {
            Zend_Registry::get('logger')->err(__CLASS__ . '->' . __FUNCTION__ . "(): strict can handle only one Property");
            return [];
        }
        if (get_class($domElements) != "DOMDocument") {
            $domDoc = new DomDocument;
            $domDoc->appendChild($domDoc->importNode($domElements, true));
        } else {
            $domDoc = $domElements;
        }
        return $this->findDomElements($domDoc, $prop, $propName, $tag, $strict);
    }

    /**
     * @param $url
     * @param string $baseUrl
     * @return mixed|string|string[]|null
     */
    public function getRedirectedUrl($url, $baseUrl = '')
    {
        $url = preg_replace('#^\/#', "$baseUrl/", $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_exec($ch);
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $url;
    }

    /**
     * @param string $url
     * @return DOMDocument
     * @throws Exception
     */
    public function getResponseAsDOM($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $page = curl_exec($ch);
        curl_close($ch);

        if (!$page) {
            $this->open($url);
            $page = $this->getPage()->getResponseBody();
        }

        $doc = new DOMDocument();
        $doc->validateOnParse = true;
        $libxml_previous_state = libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($page, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        libxml_use_internal_errors($libxml_previous_state);

        return $doc;
    }

    /**
     * @param DOMDocument $domDoc
     * @param string|array $prop
     * @param string $propName
     * @param string $tag
     * @param bool $strict
     * @return array|DOMNodeList|false
     */
    public function findDomElements($domDoc, $prop, $propName, $tag, $strict)
    {
        $props = is_array($prop) ? $prop : [$prop];
        $queryShorts = [];
        foreach ($props as $prop) {
            $queryShorts[] = $strict ?
                "contains(concat(' ', normalize-space(@$propName), ' '), ' $prop ')" :
                "contains(@$propName, '$prop')";
        }
        $finder = new DomXPath($domDoc);
        return iterator_to_array($finder->query("//$tag" . "[" . implode($queryShorts, ' or ') . "]"));
    }


    /**
     * @param string $text
     * @param string | array $pattern
     * @param string $baseUrl
     * @return array
     */
    public function getUrlsFromText($text, $pattern = '', $baseUrl = '')
    {
        $ret = [];
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        $linkPattern = 'href\=\"';
        if (!preg_match_all("#(?:https:\/\/|http:\/\/|www\.|$linkPattern)[^\s|\"|<|>]+#i", $text, $urls)) {
            return $ret;
        }
        foreach ($urls[0] as $url) {
            $url = preg_replace(["#^$linkPattern#i", '#^\/#',], ['', "$baseUrl/"], $url);
            foreach ($patterns as $pattern) {
                if ($pattern && !preg_match($pattern, $url)) {
                    continue;
                }
                $url = $this->getRedirectedUrl($url);
                if (!$this->checkUrlReachability($url)) {
                    continue;
                }
                $ret[] = $url;
                break;
            }
        }
        return array_values(array_unique($ret));
    }

    /**
     * @param $url
     * @param string $pattern
     * @param string $baseUrl
     * @return array
     * @throws Exception
     */
    public function getUrlsFromUrl($url, $pattern = '', $baseUrl = '')
    {
        $this->open($url);
        $page = $this->getPage()->getResponseBody();
        $baseUrl = $baseUrl ?: parse_url($url)['scheme'] . '://' . parse_url($url)['host'];
        return $this->getUrlsFromText($page, $pattern, $baseUrl);

    }

    /**
     * @param string $graphQlUrl
     * @param string $query
     * @return object
     * @throws Exception
     */
    public function getJsonFromGraphQL(string $graphQlUrl, string $query): object
    {
        $ch = curl_init($graphQlUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'User-Agent: Offerista-Suchdienst (+https://www.offerista.com/suchdienst/)',
                'Content-Length: ' . strlen($query))
        );

        $curl_response = curl_exec($ch);

        if (!is_object($jResponse = json_decode($curl_response))) {
            curl_close($ch);
            throw new Exception("GraphQL Call not possible: $curl_response");
        }
        curl_close($ch);

        return $jResponse;
    }
}
