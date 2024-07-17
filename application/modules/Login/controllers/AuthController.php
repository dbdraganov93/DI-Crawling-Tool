<?php
/**
 * Authentifikationscontroller des Login Plugins
 *
 * Class Login_AuthController
 */
class Login_AuthController extends Zend_Controller_Action implements Login_Check_ControllerAccessManager
{
    public function indexAction() {
        $this->forward('login');
    }

    public function loginAction()
    {
        $db = $this->_getParam('db');
        
        $loginForm = new Login_Form_Login();
        if ($this->getRequest()->isPost()
            && $loginForm->isValid($this->_request->getParams())
        ) {
            Zend_Session::namespaceUnset('errors');
            Zend_Session::namespaceUnset('infos');
            $params = $this->_request->getParams();
            $authAdapter = new Zend_Auth_Adapter_DbTable($db, 'User');
            $sUser = new Marktjagd_Database_Service_User();
            $eUser = $sUser->findByUserName($params['userName']);
            $authAdapter->setIdentity($params['userName'])
                        ->setCredential(hash('sha256', $eUser->getPasswordSalt() . $params['password']));
            $authAdapter->setIdentityColumn('userName')
                        ->setCredentialColumn('password');
            $auth = Zend_Auth::getInstance();
            $result = $auth->authenticate($authAdapter);
            $messages = $result->getMessages();

            if ($result->isValid()) {
                $userInfo = $authAdapter->getResultRowObject(null, array('password', 'passwordSalt'));
                $userInfo->role = $eUser->getRole()->toArray();
                // the default storage is a session with namespace Zend_Auth
                $authStorage = $auth->getStorage();
                $authStorage->write($userInfo);
                $this->redirect('/Crawler/index/index');
            } else {
                echo '<div class="errors">';
                foreach ($messages as $message)  {
                    echo $message . '<br />';
                }
                echo '</div>';
            }

        }

        $this->view->loginForm = $loginForm;
    }

    public function logoutAction()
    {
        $auth = Zend_Auth::getInstance();
        $authStorage = $auth->getStorage();
        $authStorage->clear();
    }

    /**
     * @param $actionName
     *
     * @return bool
     */
    public function isPublic( $actionName ) {
        switch( $actionName ) {
            case 'index':
            case 'login':
                return true;
            default:
                return false;
        }
    }

}