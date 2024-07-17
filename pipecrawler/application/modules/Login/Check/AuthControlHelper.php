<?php

/**
 * Class Login_Check_AuthControlHelper
 */
class Login_Check_AuthControlHelper extends Zend_Controller_Action_Helper_Abstract {
    /**
     * Prüft, ob der Benutzer auf die angeforderte Action zugreifen darf.
     */
    public function preDispatch() {

        if(Zend_Auth::getInstance()->hasIdentity()) {
            // Der Benutzer ist bereits eingeloggt, darf also in jedem Fall
            // auf die Aktion zugreifen.
            return;
        }

        $actionController = $this->getActionController();
        $request = $actionController->getRequest();

        if($actionController instanceof Login_Check_ControllerAccessManager
            && $actionController->isPublic($request->getActionName())
        ) {
            // Der aufgerufene Controller implementiert das ControllerAccessManager-Interface
            // und die angeforderte Aktion ist öffentlich zugänglich.
            return;
        }
        // Die Aktion ist nicht öffentlich und der aufrufende Benutzer ist nicht
        // eingeloggt. Der Benutzer wird daher auf die Login-Seite umgeleitet.
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->direct('login','auth','Login');

    }

}