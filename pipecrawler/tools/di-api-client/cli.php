#!/usr/bin/php
<?php
require_once ('MjApiClient.php');

// Example: ./cli.php --import-id=1577153 --warnings=1 --info=1
// Parse Cli-Parameters:
$options = array(
    'config:'        => '../../application/configs/apiClient.ini',
    'help:'         => null,
    'api-key:'       => 'production',
    'import-id:'    => null,
    'errors:'       => 1,
    'warnings:'     => 0,
    'infos:'        => 0,
);

$args = getopt("", array_keys($options));
if (!$args || in_array('help', $args)) {
    echo "Helper Script for calling the APIv3. Parameters:\n";
    foreach ($options as $param => $value) {
        echo "  --" . rtrim($param, ':') . ($value !== null ? " (default '$value')" : "") . "\n";
    }
    exit(0);
}

// Import default params from $options into $args
foreach ($options as $param => $value) {
    $param = rtrim($param, ':');
    if (!array_key_exists($param, $args) && $value !== null) $args[$param] = $value;
}

$ini_array = parse_ini_file($args['config'], TRUE);
if ($ini_array == FALSE) {
    throw new Exception('Config file not found: ' . $args['config'] );
}

// Configure API-Client Credentials:
MjRestRequest::setHost($ini_array[$args['api-key']]['config.host']);
MjRestRequest::setKeyId($ini_array[$args['api-key']]['config.key_id']);
MjRestRequest::setSecretKey($ini_array[$args['api-key']]['config.secret_key']);

function sendRequest($res, $par=array(), $met='get', $req=null) {
    $mj = new MjRestRequest($res, $par);
    if (!is_null($req)) $mj->setRequestBody(json_encode($req),MjRestRequest::CONTENT_TYPE_JSON);
    $mj->$met();
    $code = $mj->getResponseStatusCode();

    if ($code == 404) return false;
    if( $code<200 || $code>299 ){
        if($r = $mj->getResponse()) $message = print_r($r,true);
        else $message = print_r($mj->getResponseBody(),true);
        throw new Exception("$met $res failed ($code): ".$message);
    }

    return $mj->getResponse();
}

// Fetch Import-Resource and display all details:
$params = array();
if ($args['errors']) $params['with_errors'] = true;
if ($args['warnings']) $params['with_warnings'] = true;
if ($args['infos']) $params['with_infos'] = true;
$import = sendRequest("import/{$args['import-id']}", $params);
if (!$import) die("Import {$args['import-id']} not found or wrong API-Key\n");
foreach (array(
    'id', 'company_id', 'type', 'status', 'url', 'datetime_created', 'datetime_started', 'datetime_last_changed', 'behavior'
         ) as $attrib) {
    if (property_exists($import->import, $attrib)) printf("%-25s: %s\n", strtoupper($attrib), $import->import->{$attrib});
}
foreach (array(
    'error', 'warning', 'info'
         ) as $type) {
    $typePlural = $type.'s';
    if ($args[$typePlural] && property_exists($import->import, 'import_'.$typePlural)) {
        if (property_exists($import->import->{"import_$typePlural"}, "import_$type"))
            $messages = $import->import->{"import_$typePlural"}->{"import_$type"};
        else $messages = array();
        echo strtoupper($typePlural) . " (".count($messages)."):\n";
        foreach ($messages as $message) {
            // "User-Unsafe" Messages return the Unix-Timestamp as "time":
            if (property_exists($message, 'datetime')) $time = $message->datetime;
            else if (property_exists($message, 'time')) $time = date('c', $message->time);
            else $time = 'N/A';
            // Info-Messages like deletion of Objects have no record-field:
            if (property_exists($message, 'record')) $record = $message->record;
            else $record = 'N/A';

            printf("  %s (record:%s): %s\n", $time, $record, $message->message);
        }
    }
}
