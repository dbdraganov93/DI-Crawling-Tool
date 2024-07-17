#!/usr/bin/php
<?php
require_once __DIR__ . '/index.php';
declare(ticks=1);

function shutdown($idCrawlerLog)
{
    if ($error = error_get_last()) {
        if (isset($error['type']) && ($error['type'] == E_ERROR ||
                $error['type'] == E_PARSE ||
                $error['type'] == E_COMPILE_ERROR)
        ) {
            ob_end_clean();

            $message = 'Es ist ein Fehler aufgetreten: ' . "\n"
                . 'Message: ' . $error['message'] . "\n"
                . 'File: ' . $error['file'] . "\n"
                . 'Line: ' . $error['line'];
            /* @var $logger Zend_Log */
            $logger = Zend_Registry::get('logger');
            $logger->log($message, Zend_Log::CRIT);
            $logger->__destruct();
            $crawler = new Crawler_Generic_Crawler();
            $crawler->finishError($idCrawlerLog, $message);
        }
    }
}

//// signal handler function
function sig_handler($signo)
{
    global $idCrawlerLog;
    switch ($signo) {
        case SIGTERM:
        case SIGINT:
        case SIGHUP:
        case SIGUSR1:
            $message = 'Crawler mit Log-Id ' . $idCrawlerLog . ' terminiert ';
            $crawler = new Crawler_Generic_Crawler();
            $crawler->finishError($idCrawlerLog, $message);
            /* @var $logger Zend_Log */
            $logger = Zend_Registry::get('logger');
            $logger->log($message, Zend_Log::INFO);
            $logger->__destruct();
            exit(-1);
        default:
            // handle all other signals
    }
}

$idCrawlerLog = $argv[1];
register_shutdown_function('shutdown', $idCrawlerLog);
// Catch Ctrl+C, kill and SIGTERM (Rollback)
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');

$crawler = new Crawler_Generic_Crawler();
$crawler->start($idCrawlerLog, posix_getpid());
