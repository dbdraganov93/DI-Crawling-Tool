<?php

class Wgw_Service_Import_StoreImport
{

    protected $_aConfig;
    protected $_aHeader;
    protected $_time;
    protected $_fileName;
    protected $_idCompany;
    protected $_initialize;
    protected $_logger;
    protected $_aStoresIntegrated;

    public function __construct($idCompany)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->_initialize = new Wgw_Service_Import_InitializeImport();
        $this->_aConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', 'wgw-tmp');
        $this->_time = strtotime('now + 3600 seconds');
        $this->_idCompany = $idCompany;
        $this->_fileName = $this->_initialize->initialize();

        $sWgwStoreExport = new Wgw_Service_Export_StoreExport();
        $cStores = json_decode($sWgwStoreExport->getAllStores($this->_idCompany));

        $sAddress = new Marktjagd_Service_Text_Address();
        foreach ($cStores->data as $singleStore) {
            $this->_aStoresIntegrated[md5(
                $singleStore->attributes->postalCode
                . preg_replace('#[^\d\w]#', '', $sAddress->extractAddressPart('street', $singleStore->attributes->address)
                    . $sAddress->extractAddressPart('streetnumber', $singleStore->attributes->address)))] = $singleStore->id;
        }
    }

    protected function initialize()
    {
        $aPostfields = [
            'client_id' => $this->_aConfig->config->oauth_client_id,
            'client_secret' => $this->_aConfig->config->oauth_client_secret,
            'grant_type' => 'client_credentials'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_aConfig->config->host . '/import/v1/token');
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aPostfields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_aHeader);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($ch);

        curl_close($ch);

        $jData = json_decode($result);

        $this->_fileName = APPLICATION_PATH . '/../public/files/tmp/' . $this->_time;
        $fh = fopen($this->_fileName, 'w+');
        fwrite($fh, $jData->access_token);
        fclose($fh);

        return $this->_fileName;
    }

    public function putStores($cStoresToImport, $cleanStores = FALSE)
    {
        $aStores = [];
        if ($cleanStores) {
            $sWgwStoreExport = new Wgw_Service_Export_StoreExport();
            $cStores = json_decode($sWgwStoreExport->getAllStores($this->_idCompany));
            foreach ($cStores->data as $singleStore) {
                $aStores[$singleStore->id] = $singleStore->id;
            }
        }

        foreach ($cStoresToImport->getElements() as $eStore) {
            if ($storeNumberWgw = $this->_putStore($eStore)) {
                unset($aStores[$storeNumberWgw]);
            }
        }

        if ($cleanStores) {
            if (count($aStores)) {
                foreach ($aStores as $storeNumberWgw) {
                    $this->_logger->info('deleting store: ' . $storeNumberWgw);
                    if ($this->_doRequest($storeNumberWgw)) {
                        $this->_logger->info('deleted store: ' . $storeNumberWgw);
                    }
                }
            }
        }

    }

    protected function _putStore($eStore)
    {
        $sAddress = new Marktjagd_Service_Text_Address();
        if (array_key_exists(
            md5(
                $eStore->getZipcode()
                . preg_replace('#[^\d\w]#', '', $sAddress->normalizeStreet($eStore->getStreet())
                    . $sAddress->normalizeStreetNumber($eStore->getStreetNumber()))
            ),
            $this->_aStoresIntegrated)) {
            $this->_logger->info('patching store: ' . $this->_aStoresIntegrated[md5($eStore->getZipcode()
                    . preg_replace('#[^\d\w]#', '', $sAddress->normalizeStreet($eStore->getStreet())
                        . $sAddress->normalizeStreetNumber($eStore->getStreetNumber()))
                )]);
            $storeNumberWgw = $this->_aStoresIntegrated[md5($eStore->getZipcode()
                . preg_replace('#[^\d\w]#', '', $sAddress->normalizeStreet($eStore->getStreet())
                    . $sAddress->normalizeStreetNumber($eStore->getStreetNumber()))
            )];

            if ($this->_doRequest($eStore, $storeNumberWgw)) {
                $this->_logger->info('done.');
                return $storeNumberWgw;
            }
        } else {
            $this->_logger->info('adding store.');
            if ($this->_doRequest($eStore)) {
                $this->_logger->info('done.');
                return TRUE;
            }
        }
    }

    protected function _doRequest($eStore = NULL, $storeNo = '')
    {
        $sConvert = new Wgw_Service_Import_StoreConvert($this->_idCompany);

        if (strtotime('now') > $this->_time || !strlen($this->_fileName)) {
            $this->_time = strtotime('now + 3600 seconds');
            $this->_fileName = $this->_initialize->initialize();
        }

        $fh = fopen($this->_fileName, 'r+');
        $strBearer = fread($fh, 100);
        fclose($fh);

        $aHeader = [
            'Authorization: Bearer ' . $strBearer,
            'Content-Type: application/json'
        ];

        $ch = curl_init();

        $storeUrl = $this->_aConfig->config->host . '/import/v1/stores';
//        $storeUrl = 'https://api.wogibtswas.at' . '/import/v1/stores';

        if (is_a($eStore, 'Marktjagd_Entity_Api_Store')) {
            if (strlen($storeNo)) {
                $storeUrl .= "/$storeNo";
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                $strError = 'patched';
                $iHttpCode = 200;
            } else {
                curl_setopt($ch, CURLOPT_POST, TRUE);
                $strError = 'created';
                $iHttpCode = 201;
            }

            $aPostfields = $sConvert->convertStore($eStore);

            if (!$aPostfields) {
                return FALSE;
            }

        } else {
            $storeUrl .= "/$storeNo";
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            $strError = 'deleted';
            $iHttpCode = 204;
        }

        curl_setopt($ch, CURLOPT_URL, $storeUrl);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aPostfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);


        Zend_Debug::dump('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
        Zend_Debug::dump('$storeUrl:');
        Zend_Debug::dump($storeUrl);
        Zend_Debug::dump('$aPostfields:');
        Zend_Debug::dump($aPostfields);
        Zend_Debug::dump('$info:');
        Zend_Debug::dump($info);
        Zend_Debug::dump('$result:');
        Zend_Debug::dump($result);
        Zend_Debug::dump('<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<');

        if ($info['http_code'] != $iHttpCode) {
            throw new Exception('store couldn\'t be ' . $strError . ': ' . $result);
        }

        return TRUE;
    }

}