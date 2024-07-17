<?php

require APPLICATION_PATH . '/../vendor/autoload.php';

class Marktjagd_Service_Output_GoogleAuth
{
    public function getClient($scope = Google_Service_Sheets::SPREADSHEETS)
    {
        $logger = Zend_Registry::get('logger');
        $client = new Google_Client();
        $client->setScopes($scope);
        $client->setAuthConfig(APPLICATION_PATH . '/configs/googleCredentials.json');
        $client->setAccessType('offline');

        $tokenPath = APPLICATION_PATH . '/configs/googleAccessToken.json';
        if (file_exists($tokenPath) && filesize($tokenPath) > 0) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                $logger->log("Open the following link in your browser:\n\n" . $authUrl, Zend_Log::INFO);

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode('4/4AHj4mU0dqbBQZZnDDUsqEK9yHkyRM0zLdJBokYtuq70BULxbwsiNx4');
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(implode(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

}