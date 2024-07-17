<?php

/**
 * Bootstrap Klasse für das Crawler Modul
 *
 * Class Crawler_Bootstrap
 */
class Crawler_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Bootstrap autoloader for application resources
     */
    protected function _initAutoload()
    {
        $oAutoloader = new Zend_Application_Module_Autoloader(array('namespace' => 'Crawler',
            'basePath'  => dirname(__FILE__)));

        $oAutoloader->addResourceTypes(
            array('collections' => array(
                'namespace' => 'Model_Collection',
                'path'      => 'models/collections',
                ),
                'entities' => array(
                    'namespace' => 'Model_Entity',
                    'path'      => 'models/entities',
                ),
                'services' => array(
                    'namespace' => 'Model_Service',
                    'path'      => 'models/services',
                ),
                'crawler' => array(
                    'namespace' => 'Company',
                    'path'      => 'companies',
                ),
                'generic' => array(
                    'namespace' => 'Generic',
                    'path'      => 'generic',
                ),
            ));

        return $oAutoloader;
    }
}