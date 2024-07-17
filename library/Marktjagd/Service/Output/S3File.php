<?php

// Include the SDK using the Ubuntu package
require_once APPLICATION_PATH . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\ObjectUploader;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\CredentialProvider;
use Aws\Sts\StsClient;

/**
 * Service zum Schreiben eines Files nach S3
 */
class Marktjagd_Service_Output_S3File
{
    protected $_filePrefix;
    protected $_filePath;
    protected $_fileURL;
    protected $_s3Config;
    protected $_s3Client;

    /**
     * Der Konstruktor bereitet den S3 Client vor, mit dem mit der S3 API kommuniziert wird.
     * @param string $filePrefix Pfad, der nach dem globalen präfix aus crawler.s3.prefix aus der application.ini angehängt wird.
     * @param string $fileName Dateiname inkl. Endung
     * @param bool $isForPinterest The file will be saved in a separate bucket for Pinterest
     */
    public function __construct($filePrefix, $fileName, $isForPinterest = false)
    {
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        $this->_s3Config = $isForPinterest ? $configCrawler->crawler->pinterest : $configCrawler->crawler->s3;

        // Prepare file name for S3.
        $this->_filePrefix = $this->_s3Config->prefix . $filePrefix;
        $this->_filePath = ltrim($this->_filePrefix . $fileName, '/');

        // Get AWS api-credentials, either from the instance profile (when running on an AWS-server) or from
        // environment variables (when running locally for testing):
        $accessKeyId = getenv('AWS_ACCESS_KEY_ID');
        $secretAccessKey = getenv('AWS_SECRET_ACCESS_KEY');
        $accessRole = getenv('AWS_ACCESS_ROLE');
        if ($accessKeyId && $secretAccessKey && $accessRole) {
            // When running locally, use the user's API-key:
            $credentials = [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ];

            // Assume different roles in a chain to first access the account and then possibly the role with witch the
            // application runs.
            // XXX: This is not possible at the moment however, since the application does not have an assumable role,
            // but runs with the iam-profile of the orchestrator-instance…
            $roleChain = [
                $accessRole,  // First assume the role with which the user accesses the AWS-account.
                // XXX: Additional roles would go in here…
            ];

            // Assume one role after another, each creating a new credentials-pair:
            foreach ($roleChain as $roleArn) {
                $credentials = CredentialProvider::assumeRole([
                    'client' => new StsClient([
                        'region' => $this->_s3Config->region,
                        'version' => 'latest',
                        'credentials' => $credentials,
                    ]),
                    'assume_role_params' => [
                        'RoleArn' => $roleArn,
                        'RoleSessionName' => 'assumed-role',
                    ],
                ]);
            }
            $memoizedProvider = CredentialProvider::memoize($credentials);
        } else {
            $provider = CredentialProvider::instanceProfile();
            $memoizedProvider = CredentialProvider::memoize($provider);
        }

        // Prepare client for S3 API.
        $this->_s3Client = New S3Client([
            'region' => $this->_s3Config->region,
            'version' => $this->_s3Config->apiVersion,
            'credentials' => $memoizedProvider
        ]);
    }

    /**
     * Speichert den übergebenen Content in einem S3 Objekt und gibt die URL dieser Datei zurück.
     * Im Fehlerfall wird false zurückgegeben.
     *
     * @param string $content Inhalt, der in die Datei geschrieben werden soll
     * @return boolean|string
     */
    public function saveContentInFile($content)
    {
        $logger = Zend_Registry::get('logger');
        try {
            $uploader = new ObjectUploader($this->_s3Client, $this->_s3Config->bucketname, $this->_filePath, $content, 'private', array(
                'params' => array(
                    'ContentType' => (pathinfo($this->_filePath, PATHINFO_EXTENSION) == 'csv' ? 'text/csv' : 'text/plain'),
                )));
            $result = $uploader->upload();
        } catch (S3Exception $e) {
            $logger->log("In S3 Bucket '{$this->_s3Config->bucketname}' mit Pfad '{$this->_filePath}' konnte nicht gespeichert werden.", Zend_Log::CRIT);
            return false;
        }
        $targetURL = urldecode($result['ObjectURL']);
        $logger->log("Neue Datei gespeichert nach: '{$targetURL}'", Zend_Log::INFO);
        return $this->_fileURL = $targetURL;
    }

    /**
     * Speichert die lokal verfügbare Datei in S3 ab.
     * Im Fehlerfall wird false zurückgegeben.
     *
     * @param string $localFilePath Pfad zur lokal verfügbaren Datei.
     * @return boolean|string
     */
    public function saveFileInS3($localFilePath, $retries = 3)
    {
        $logger = Zend_Registry::get('logger');
        try {
            $fileStream = fopen($localFilePath, 'r');
            $uploader = new ObjectUploader($this->_s3Client, $this->_s3Config->bucketname, $this->_filePath, $fileStream, 'private', array(
                'params' => array(
                    'ContentType' => (pathinfo($localFilePath, PATHINFO_EXTENSION) == 'csv' ? 'text/csv' : mime_content_type($localFilePath)),
                )));
            $result = $uploader->upload();
            fclose($fileStream);
        } catch (Exception $e) {
            if ($retries-- > 0) {
                sleep(1);
                return $this->saveFileInS3($localFilePath, $retries);
            }
            $logger->log("Die Datei '{$localFilePath}' konnte nicht in S3 mit Pfad '{$this->_s3Config->bucketname}:{$this->_filePath}' gespeichert werden.", Zend_Log::CRIT);
            return false;
        }
        $targetURL = urldecode($result['ObjectURL']);
        $logger->log("Datei '{$localFilePath}' gespeichert nach: '{$targetURL}'", Zend_Log::INFO);
        return $this->_fileURL = $targetURL;
    }

    /**
     * Gibt die URL zur Datei zurück
     *
     * @return string
     */
    public function getFileURL()
    {
        return $this->_fileURL;
    }

    public function removeFileFromBucket($fileUrl)
    {
        return $this->_s3Client->deleteObject([
            'Bucket' => $this->_s3Config->bucketname,
            'Key' => preg_replace('#https.+?' . $this->_s3Config->bucketname . '\/#', '', $fileUrl)
        ]);
    }

    public function getFileFromBucket($fileUrl, $localPath)
    {
        $this->_s3Client->getObject(
            array(
                'Bucket' => $this->_s3Config->bucketname,
                'Key' => preg_replace('#https.+?' . $this->_s3Config->bucketname . '\/#', '', $fileUrl),
                'SaveAs' => $localPath . basename($fileUrl)
            )
        );

        return $localPath . basename($fileUrl);
    }

    /**
     * Funktion, um File im S3-Bucket zu verschieben
     * @param string $filePrefix Präfix, der dem File vorgesetzt werden soll, analog zum Unterordner
     * @param string $fileUrlSource Ursprungs-URL im S3
     * @param string $fileUrlDestination Ziel-Url im S3
     */
    public function moveFileInBucket($filePrefix, $fileUrlSource, $fileUrlDestination)
    {
        $this->_s3Client->copyObject(
            [
                'Bucket' => $this->_s3Config->bucketname,
                'Key' => preg_replace('#\/\/#', '/', $filePrefix) . basename($fileUrlDestination),
                'CopySource' => $this->_s3Config->bucketname . preg_replace('#\/\/#', '/', $filePrefix) . basename($fileUrlSource)
            ]
        );
    }

    public function generateLocalDownloadFolder($companyId)
    {
        $localFolderName = APPLICATION_PATH . '/../public/files/s3/' . $companyId . '/' . date('Y-m-d-H-i-s') . '/';
        if (!is_dir($localFolderName)) {
            if (!mkdir($localFolderName, 0775, true)) {
                $this->_logger->log('generic http-crawler for company ' . $companyId . "\n"
                    . 'unable to create local folder for http-download', Zend_Log::CRIT);
                return false;
            }
        }

        return $localFolderName;
    }


}
