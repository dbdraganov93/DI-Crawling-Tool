<?php

class Wgw_Service_Import_InitializeImport
{
    protected $_aConfig;
    protected $_aHeader;

    public function __construct()
    {
        $this->_time = strtotime('now + 3600 seconds');
        $this->_aConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', 'wgw-tmp');
        $this->_aHeader = [
            'Authorization: Bearer ' . $this->_aConfig->config->access_token
        ];
    }

    public function initialize()
    {
        $aPostfields = [
            'client_id' => $this->_aConfig->config->oauth_client_id,
            'client_secret' => $this->_aConfig->config->oauth_client_secret,
            'grant_type' => 'client_credentials'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_aConfig->config->host . '/import/v1/token');
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
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
}