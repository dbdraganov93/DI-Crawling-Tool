<?php

/**
 * Bootstrap Klasse für das Login Modul
 *
 * Class Login_Bootstrap
 */
class Login_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Bootstrap autoloader for application resources
     */
    protected function _initAutoload()
    {
        $oAutoloader = new Zend_Application_Module_Autoloader(array('namespace' => 'Login',
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
                'login' => array(
                    'namespace' => 'Check',
                    'path'      => 'Check',
                ),
                'generic' => array(
                    'namespace' => 'Generic',
                    'path'      => 'generic',
                ),
            ));

        return $oAutoloader;
    }

    public function _initLogin()
    {
        Zend_Controller_Action_HelperBroker::addHelper(new Login_Check_AuthControlHelper());
    }
}