<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    public function _initLogger()
    {
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', $this->getEnvironment());

        Zend_View_Helper_PaginationControl::setDefaultViewPartial('controls.phtml');
        
        // Konsolenlog initialisieren
        $writerStream = new Zend_Log_Writer_Stream('php://output');
        $writerStream->addFilter((int) $configIni->log->console->level);

        $writerMock = new Zend_Log_Writer_Mock();
        $writerMock->addFilter((int) $configIni->log->mail->level);


        // Mail-Logging initialisieren
        $configMail = array(
            'auth'      => 'login',
            'username'  => $configIni->mail->smtp->user,
            'password'  => $configIni->mail->smtp->pass,
            'ssl'       => 'tls',
            'port'      => $configIni->mail->smtp->port
        );
        $smtp = new Zend_Mail_Transport_Smtp($configIni->mail->smtp->host, $configMail);

        $mail = new Zend_Mail('utf-8');
        $mail->setFrom($configIni->log->mail->from)
             ->addTo($configIni->log->mail->to)
             ->addCc($configIni->log->mail->cc)
             ->setSubject('Meldungen vom Crawler')
             ->setDefaultTransport($smtp);

        $writerMail = new Zend_Log_Writer_Mail($mail);

        $writerMail->addFilter((int) $configIni->log->mail->level);

        // Writer zum Logger hinzufügen
        $logger = new Zend_Log();
        $logger->addWriter($writerStream);
        $logger->addWriter($writerMail);
        $logger->addWriter($writerMock);
        Zend_Registry::set('logger', $logger);
        Zend_Registry::set('loggerMock', $writerMock);
    }
}

