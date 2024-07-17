<?php

/**
 * Service zum Transferieren von Dateien
 *
 * Class Marktjagd_Service_Transfer_Download
 */
class Marktjagd_Service_Transfer_Download extends Marktjagd_Service_Transfer_Http
{
    /**
     * Lädt eine Datei von einer URL herunter und erkennt dabei das Protokoll
     *
     * @param $remoteUrl
     * @param $localPath
     * @return bool|string
     */
    public function downloadByUrl($remoteUrl, $localPath)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        // http - Handling
        if (preg_match('#^http[s]*\:#', $remoteUrl)) {
            if ($this->getRemoteFile($remoteUrl, $localPath)) {
                $parsedUrl = parse_url($remoteUrl);
                if (isset($parsedUrl['path'])) {
                    $path = $parsedUrl['path'];
                } else {
                    // the url is pointing to the host like http://www.mysite.com
                    $path = '/';
                }

                $aUrl = explode('/', $path);
                $fileName = end($aUrl);
                return $localPath . $fileName;
            }
        } else {
            // ftp - Handling
            $config = array();
            $file = '';
            // ftp mit Login
            if (preg_match('#^ftp://([^:]+):([^@]+)@([a-z0-9\.]+)/(.*)$#i', $remoteUrl, $matches)) {
                $config['username'] = urldecode($matches[1]);
                $config['password'] = urldecode($matches[2]);
                $config['hostname'] = urldecode($matches[3]);
                $config['port'] = 21;
                $file = urldecode($matches[4]);
            } elseif (preg_match('#^ftp://([a-z0-9\.]+)/(.*)$#i', $remoteUrl, $matches)) {
                // ftp ohne Login
                $config['port'] = 21;
                $config['hostname'] = urldecode($matches[3]);
                $file = urldecode($matches[2]);
            } else {
                $logger->log(
                    'Das Format der URL ' . $remoteUrl . ' wird nicht unterstützt.',
                    Zend_Log::CRIT);
            }

            $sDownload = new Marktjagd_Service_Transfer_Ftp();
            $sDownload->connect($config);

            $localFileName = uniqid() . '.pdf';
            if ($sDownload->download($file, $localPath . $localFileName)) {
                return $localPath . $localFileName;
            }
        }

        return false;
    }
}