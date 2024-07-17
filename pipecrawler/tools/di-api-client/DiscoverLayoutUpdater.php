#!/usr/bin/php
<?php

require_once ('MjApiClient.php');

/*
 * This script can be used to update the Discover layout_string of a brochure.
 * To do so, one needs to manually update the configuration in this script,
 * save and execute it.
 */

// Configuration

$apiHost = 'https://legacy-api-elb.ws-stage.offerista.com';
$apiKeyId = 'XYZ';
$apiSecretKey = 'XYZ';

$brochureId = '1234567';
// To remove the layout string of a brochure, set $layoutString = null;
$layoutString = '{}';

MjRestRequest::setHost($apiHost);
MjRestRequest::setKeyId($apiKeyId);
MjRestRequest::setSecretKey($apiSecretKey);

function sendRequest($res, $par=array(), $met='get',  $req=null) {
    $mj = new MjRestRequest($res, $par);
    if (!is_null($req)) $mj->setRequestBody(json_encode($req), MjRestRequest::CONTENT_TYPE_JSON);
    $mj->$met();
    $code = $mj->getResponseStatusCode();
    if( $code<200 || $code>299 ){
        if($r = $mj->getResponse()) $message = print_r($r,true);
        else $message = print_r($mj->getResponseBody(), true);
        throw new Exception("$met $res failed ($code): ".$message);
    }
    return $mj->getResponse();
}

$ret = sendRequest("brochure/" . $brochureId, [], 'post', [
    'brochure' => [
        'layout' => $layoutString
    ]
]);

var_dump($ret);
