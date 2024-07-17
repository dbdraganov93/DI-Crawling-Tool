<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';

class IprotoApiClient
{
    /**
     * Logging object
     *
     * @var Zend_Log
     */
    protected $_logger;
    private $environment;
    private $tempTokenFile;
    private $config;

    /**
     * @param string $environment defines the API environment which should be used, e.g. staging or production
     * @throws Zend_Exception
     * @throws RuntimeException
     */
    public function __construct(string $environment)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->environment = $environment;
        $this->tempTokenFile = '/srv/efs/iproto_api_token-'.$environment;
        $this->config = new Zend_Config_Ini(APPLICATION_PATH.'/configs/apiClient.ini', $environment);
        $this->setupApiToken();
    }

    private function setupApiToken()
    {
        // Try to fetch the token from the database, if it is not already cached locally:
        if (!file_exists($this->tempTokenFile)) {
            $tokenTable = new Marktjagd_Database_DbTable_IProtoApiToken();
            $token = $tokenTable->findByEnv($this->environment);
            if ($token !== null) {
                // Fetch token from the DB:
                file_put_contents($this->tempTokenFile, $token->toArray()['IProtoApiToken.token']);
            } else {
                // No token in the db, create a new one:
                $this->createToken();
                $tokenTable->insertToken($this->environment, file_get_contents($this->tempTokenFile));
            }
        }

        // Check if the token has expired:
        if (!$this->isTokenNotExpired()) {
            // Refresh the token and update it in the database:
            $this->createToken();
            $tokenTable = new Marktjagd_Database_DbTable_IProtoApiToken();
            $tokenTable->updateToken($this->environment, file_get_contents($this->tempTokenFile));
        }
    }

    private function createToken()
    {
        $timestamp = time();
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->config->config->iproto->auth0->host,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"client_id\":\"".$this->config->config->iproto->auth0->clientId."\",\"client_secret\":\"".$this->config->config->iproto->auth0->clientSecret."\",\"audience\":\"backend\",\"grant_type\":\"client_credentials\"}",
            CURLOPT_HTTPHEADER => [
                "content-type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new RuntimeException($err);
        }

        $responseObject = json_decode($response);
        $resource = fopen($this->tempTokenFile, 'w');
        if (!$resource) {
            throw new RuntimeException('unable to open tmp file'.$this->tempTokenFile. 'for iproto API token');
        }

        $content = ($timestamp + $responseObject->expires_in).PHP_EOL.$responseObject->access_token;
        flock($resource, LOCK_EX);
        if (fwrite($resource, $content) === FALSE) {
            throw new RuntimeException('unable to write to tmp file'.$this->tempTokenFile. 'for iproto API token');
        }

        fflush($resource);
        flock($resource, LOCK_UN);
        fclose($resource);
    }

    private function isTokenNotExpired(): bool
    {
        if (!$resource = fopen($this->tempTokenFile, 'r')) {
            throw new RuntimeException('unable to open tmp file'.$this->tempTokenFile. 'for iproto API token');
        }

        flock($resource, LOCK_SH);
        $ret = (time() <= intval(fgets($resource)) + 10);
        flock($resource, LOCK_UN);
        fclose($resource);

        return $ret;
    }

    public function getIprotoApiToken()
    {
        $this->setupApiToken();

        if (!$resource = fopen($this->tempTokenFile, 'r')) {
            throw new RuntimeException('unable to open tmp file'.$this->tempTokenFile. 'for iproto API token');
        }

        flock($resource, LOCK_SH);
        fgets($resource); // skipping the first line containing the TTL timestamp
        $token = fgets($resource);
        flock($resource, LOCK_UN);
        fclose($resource);

        return $token;
    }
}
