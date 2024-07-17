<?php

class Wgw_Service_Export_StoreExport
{

    protected $_aConfig;
    protected $_aHeader;
    protected $_time;
    protected $_fileName;
    protected $_initialize;

    public function __construct()
    {
        $this->_initialize = new Wgw_Service_Import_InitializeImport();
        $this->_aConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', 'wgw-tmp');
        $this->_time = strtotime('now + 3600 seconds');
        $this->_fileName = $this->_initialize->initialize();
    }

    public function getStore($storeNo)
    {
        return $this->_doGetRequest('/' . $storeNo);
    }

    public function getAllStores($idCompany)
    {
        return $this->_doGetRequest('?filter[company]=' . $idCompany . '&page[limit]=2500');
    }

    protected function _doGetRequest($url)
    {
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
        curl_setopt($ch, CURLOPT_URL, $this->_aConfig->config->host . '/import/v1/stores' . $url);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }
}