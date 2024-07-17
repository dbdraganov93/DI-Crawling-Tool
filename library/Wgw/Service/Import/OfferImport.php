<?php

class Wgw_Service_Import_OfferImport
{
    protected $_aConfig;
    protected $_time;
    protected $_fileName;
    protected $_idCompany;
    protected $_initialize;
    protected $_logger;

    public function __construct($idCompany)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->_initialize = new Wgw_Service_Import_InitializeImport();
        $this->_aConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', 'wgw-tmp');
        $this->_time = strtotime('now + 3600 seconds');
        $this->_idCompany = $idCompany;
        $this->_fileName = $this->_initialize->initialize();
    }

    public function putOffer($eOffer)
    {
        $sConvert = new Wgw_Service_Import_OfferConvert($this->_idCompany);

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

        $aPostfields = $sConvert->convertEntity($eOffer);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_aConfig->config->host . '/import/v1/offers');
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aPostfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);
        if ($info['http_code'] != 201) {
            Zend_Debug::dump($eOffer);
            $this->_logger->log('offer couldn\'t be created: ' . $result, Zend_Log::INFO);
        }

        return $result;
    }

}