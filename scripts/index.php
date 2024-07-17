<?php

// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
if(!defined('APPLICATION_ENV')) {
    if (preg_match('#init-dev-machine-\w+#', getenv()['PWD'])) {
        define('APPLICATION_ENV', 'development');
    } else {
        define('APPLICATION_ENV', 'production');
    }
}

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

// bootstrap and retrieve the frontController resource
$front = $application->getBootstrap()
                     ->bootstrap('frontController')
                     ->getResource('frontController');

//Which part of the app we want to use?
$module     = 'Crawler';
$controller = 'index';
$action     = 'cli';

//create the request
$request = new Zend_Controller_Request_Simple($action, $controller, $module);



// set front controller options to make everything operational from CLI
$front->setRequest($request)
      ->setResponse(new Zend_Controller_Response_Cli())
      ->setRouter(new Marktjagd_Service_Zend_Cli())
      ->throwExceptions(true);

// lets bootstrap our application and enjoy!
$application->bootstrap();

// Login für CLI deaktivieren
Zend_Controller_Action_HelperBroker::removeHelper('AuthControlHelper');
$application->run();