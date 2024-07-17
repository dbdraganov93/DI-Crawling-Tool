<?php
/**
 * Service zum Transferieren von Dateien via SFTP
 *
 * Class Marktjagd_Service_Transfer_Sftp
 */
class Marktjagd_Service_Transfer_Sftp
{
    protected $_hostname = '';
    protected $_username = '';
    protected $_password = '';
    protected $_port = 22;
    protected $_connId = FALSE;
    protected $_sftp = FALSE;
    protected $_logger = FALSE;

    /**
     * Constructor - Sets Preferences
     *
     * The constructor can be passed an array of config values
     */
    public function __construct($config = array())
    {
        /* @var $logger Zend_Log */
        $this->_logger = Zend_Registry::get('logger');
        if (count($config) > 0) {
            $this->initialize($config);
        }

        $this->_logger->log('SFTP Class Initialized', Zend_Log::DEBUG);
    }

    /**
     * Initialize preferences
     *
     * @param    $config array
     * @return    void
     */
    public function initialize($config = array())
    {
        foreach ($config as $key => $val) {
            if (substr($key, 0, 1) != '_') {
                $key = '_' . $key;
            }

            if (isset($this->$key)) {
                $this->$key = $val;
            }
        }

        // Prep the hostname
        $this->_hostname = preg_replace('|.+?://|', '', $this->_hostname);
    }

    /**
     * FTP Connect
     *
     * @param    array $config the connection values
     * @return    bool
     */
    public function connect($config = array())
    {
        if (count($config) > 0) {
            $this->initialize($config);
        }

        if (FALSE === ($this->_connId = @ssh2_connect($this->_hostname, $this->_port))) {
            $this->_logger->err('sftp_unable_to_connect');
            return FALSE;
        }

        if (!$this->_login()) {
            $this->_logger->err('sftp_unable_to_login');
            return FALSE;
        }

        return TRUE;
    }

    /**
     * FTP Login
     *
     * @return    bool
     */
    private function _login()
    {
        @ssh2_auth_password($this->_connId, $this->_username, $this->_password);
        return $this->_sftp = @ssh2_sftp($this->_connId);
    }

    /**
     * Validates the connection ID
     *
     * @return    bool
     */
    private function _isConn(): bool
    {
        if (!is_resource($this->_connId)) {
            $this->_logger->log('sftp_no_connection', Zend_Log::ERR);
            return FALSE;
        }
        return TRUE;
    }

    public function listFiles($remote_dir) {
        if (!$this->_isConn()) {
            return FALSE;
        }

        $sftp = $this->_sftp;
        $realpath = ssh2_sftp_realpath($sftp, $remote_dir);
        $dir = "ssh2.sftp://$sftp$realpath/";
        $tempArray = array();

        fwrite(STDOUT, "Listing [${remote_dir}] ...\n\n");

        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    $tempArray[] = $file;
                }
                closedir($dh);
            }
        }
        return $tempArray;
    }

    public function downloadFile($remote_file,$localPath)
    {
        fwrite(STDOUT, "Downloading [${remote_file}] to [${localPath}] ...\n");

        $realpath = ssh2_sftp_realpath($this->_sftp, $remote_file);
        $stream = @fopen("ssh2.sftp://$this->_sftp$realpath", 'r');
        stream_set_chunk_size($stream, 1024*1024);
        if (!$stream) {
            throw new Exception("Could not open file: $localPath");
        }
        file_put_contents ($localPath . basename($remote_file), stream_get_contents($stream));
        @fclose($stream);
        return $localPath . basename($remote_file);
    }



    /**
     * Generiert einen lokalen Downloadpfad für FTP-Downloads und legt ggfs. diesen Pfad an
     *
     * @param int $companyId
     * @return string
     */
    public function generateLocalDownloadFolder($companyId)
    {
        $localFolderName = APPLICATION_PATH . '/../public/files/ftp/' . $companyId . '/' . date('Y-m-d-H-i-s') . '/';
        if (!is_dir($localFolderName)) {
            if (!mkdir($localFolderName, 0775, true)) {
                $this->_logger->log('generic ftp-crawler for company ' . $companyId . "\n"
                    . 'unable to create local folder for ftp-download:' . $localFolderName, Zend_Log::CRIT);
                return false;
            }
        }

        return $localFolderName;
    }

    public function close()
    {
        @ssh2_disconnect($this->_connId);
    }



}