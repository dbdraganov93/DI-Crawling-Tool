<?php

require '../library/Marktjagd/Service/IprotoApi/ApiServiceIproto.php'; // Adjust the path as necessary

use Marktjagd\Service\IprotoApi\ApiServiceIproto;

$api = new ApiServiceIproto();
$method = 'POST';
$uri = 'api/imports';
$params = []; // No URL parameters for this request
$body = json_encode([
    "integration" => "/api/integrations/70960",
    "type" => "brochures:api3",
    "integrationOptions" => [
        "appendOnly" => true,
        "ignoreIfNotStaged" => true
    ],
    "url" => "s3://content.di-prod.offerista/mjcsv/brochures_70960_20240715170128.csv",
    "memory" => 1024,
    "executionTimeout" => 7200
]);
$bodyMediaType = 'application/ld+json';

$response = $api->sendRequest($method, $uri, $params, $body, $bodyMediaType);
print_r($response);
