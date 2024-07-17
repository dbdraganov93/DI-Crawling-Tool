<?php

/**
 * spezifische Marktjagd FTP-Funktionalitäten
 *
 * Class Marktjagd_Service_Transfer_FtpMarktjagd
 */
class Marktjagd_Service_Transfer_FtpMarktjagd extends Marktjagd_Service_Transfer_Ftp
{
    /**
     * @var Zend_Log
     */
    protected $_logger;

    /**
     * Stellt eine Verbindung direkt mit dem Marktjagd-FTP her
     * wenn $companyId gesetzt, wird automatisch in diesem Ordner gewechselt
     *
     * @param int $companyId Unternehmens-ID
     * @param bool $generateFolder soll lokaler Ordner erstellt werden?
     * @return bool|string
     */
    public function connect($companyId = null, $generateFolder = FALSE)
    {
        $this->_logger = Zend_Registry::get('logger');
        if (!parent::connect($this->getMjFtpConfigNew())) {
            $this->_logger->log('Couldn\'t connect to MJ-FTP', Zend_Log::CRIT);
            return false;
        }

        if ($companyId) {
            if (!$this->changedir('/' . $companyId)) {
                return false;
            }
        }

        if ($generateFolder && strlen($companyId)) {
            return $this->generateLocalDownloadFolder($companyId);
        }

        return true;
    }

    /**
     * Transformiert alle notwendigen URL-Felder einer MJ-Collection von FTP zu Public URLs
     * und lädt die entsprechenden Dateien in ein lokales Downloadverzeichnis
     *
     * @param Marktjagd_Collection_Api_Abstract $cMjCsv
     * @param int $companyId
     * @param string $type
     * @param string $locFolderName
     */
    public function transformCollection(&$cMjCsv, $companyId, $type, $locFolderName)
    {
        $aElements = $cMjCsv->getElements();
        $cMjCsv->clearElements();
        foreach ($aElements as &$eMjCsv) {
            switch ($type) {
                case 'articles':
                    $this->_transformArticle($eMjCsv, $locFolderName, $companyId);
                    break;
                case 'stores':
                    $this->_transformStore($eMjCsv, $locFolderName, $companyId);
                    break;
                case 'brochures':
                    $this->_transformBrochure($eMjCsv, $locFolderName, $companyId);
                    break;
                default:
                    $this->_logger->log('generic ' . $type . ' ftp-crawler' . "\n"
                        . 'wrong type given for transformation: ' . $type, Zend_Log::CRIT);
                    break;
            }
        }

        $cMjCsv->addElements($aElements);
    }

    /**
     * Transformiert alle URL-Felder eines Artikel-Entities von FTP zu Public URLs
     *
     * @param Marktjagd_Entity_Api_Article $eArticle
     * @param string $locFolderName
     * @param int $companyId
     */
    protected function _transformArticle(&$eArticle, $locFolderName, $companyId)
    {
        $imageUrl = $eArticle->getImage();
        if (strlen($imageUrl)
            && !preg_match('#^(http|ftp)#', $imageUrl)
        ) {
            $imageUrl = $this->downloadFtpToDir($companyId . $imageUrl, $locFolderName);
            $imageUrl = $this->generatePublicFtpUrl($imageUrl);
            $eArticle->setImage($imageUrl);
        }
    }

    /**
     * Transformiert alle URL-Felder eines Store-Entities von FTP zu Public URLs
     *
     * @param Marktjagd_Entity_Api_Store $eStore
     * @param string $locFolderName
     * @param int $companyId
     */
    protected function _transformStore(&$eStore, $locFolderName, $companyId)
    {
        $logoUrl = $eStore->getLogo();
        if (strlen($logoUrl)
            && !preg_match('#^(http|ftp)#', $logoUrl)) {
            $logoUrl = $this->downloadFtpToDir($companyId . $logoUrl, $locFolderName);
            $logoUrl = $this->generatePublicFtpUrl($logoUrl);
            $eStore->setLogo($logoUrl);
        }


        $imageUrl = $eStore->getImage();
        if (strlen($imageUrl)
            && !preg_match('#^(http|ftp)#', $imageUrl)) {
            $imageUrl = $this->downloadFtpToDir($companyId . $imageUrl, $locFolderName);
            $imageUrl = $this->generatePublicFtpUrl($imageUrl);
            $eStore->setImage($imageUrl);
        }
    }

    /**
     * Transformiert alle URL-Felder eines Brochure-Entities von FTP zu Public URLs
     *
     * @param Marktjagd_Entity_Api_Brochure $eBrochure
     * @param string $locFolderName
     * @param int $companyId
     */
    protected function _transformBrochure(&$eBrochure, $locFolderName, $companyId)
    {
        $url = $eBrochure->getUrl();
        if (strlen($url)
            && !preg_match('#^(http|ftp)#', $url)) {
            $url = $this->downloadFtpToDir($companyId . $url, $locFolderName);
            $url = $this->generatePublicFtpUrl($url);
            $eBrochure->setUrl($url);
        }
    }
}