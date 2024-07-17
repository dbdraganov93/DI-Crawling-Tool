#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

if (count($argv) < 3
    || !$argv[1]
) {
    $logger->log($argv[0] . ' $PATHTOSCRIPT $companyId', Zend_Log::INFO);
    $logger->log("Startet das ausgewählte Crawlerscript", Zend_Log::INFO);
    die;
}

$companyId = $argv[2];
if (!is_numeric($companyId)) {
    $logger->log('CompanyId ist kein numerischer Wert: ' . $companyId, Zend_Log::INFO);
}

$className = str_replace('application/modules/', '', $argv[1]);
$className = str_replace('.php', '', $className);

if (!preg_match('#(generic|Generic)#', $className)
    && !preg_match('#(companies|Company)#', $className)
) {
    if (substr($className, 0, 1) == '/') {
        $className = 'Crawler/companies' . $className;
    } else {
        $className = 'Crawler/companies/' . $className;
    }
}

$className = str_replace('companies', 'Company', $className);
$className = str_replace('generic', 'Generic', $className);
$className = str_replace('/', '_', $className);

$beginn = microtime(true);

/* @var $crawler Crawler_Generic_Company */
$crawler = new $className();
$response = $crawler->crawl($companyId);


$url = $response->getFileName();
if (preg_match('#^https://s3.eu-west-1.amazonaws.com/(.*)$#', $url, $matches)) {
    // Use the S3-protocol directly instead of going through http first (which would involve leaving the private cloud from a network perspective):
    $url = 's3://'.$matches[1];
    $response->setFileName($url);
}

$aResponse = array(
    'loggingCode' => $response->getLoggingCode(),
    'fileName' => $response->getFileName(),
    'isImport' => $response->getIsImport(),
    'importId' => $response->getImportId(),
);

$jResponse = json_encode($aResponse);

$responseFilePath = APPLICATION_PATH . '/../public/files/ofjson/response_' . $companyId . '_' . date('YmdHim') . '.json';

$fh = fopen($responseFilePath, 'w+');
fwrite($fh, $jResponse);
fclose($fh);

$dauerSec = microtime(true) - $beginn;

Zend_Debug::dump($responseFilePath);
Zend_Debug::dump($response);
Zend_Debug::dump("$argv[1]: Runtime: " . (int)($dauerSec / 60) . " min, " . ($dauerSec % 60) . " sec");
exit(0);