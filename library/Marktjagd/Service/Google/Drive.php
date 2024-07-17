<?php

require APPLICATION_PATH . '/../vendor/autoload.php';

use Google\Service\Drive;

class Marktjagd_Service_Google_Drive
{

    private const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';
    private const GOOGLE_MIME_TYPES_TO_REGULAR = [
        'application/vnd.google-apps.photo' => 'image/jpeg',
        'application/vnd.google-apps.document' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.google-apps.presentation' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.google-apps.spreadsheet' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    private const MIME_TYPE_TO_EXT_MAP = [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'text/csv' => 'csv',
        'application/pdf' => 'pdf',
        'application/rtf' => 'rtf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'text/html' => 'html',
        'text/plain' => 'txt',
        'application/json' => 'json'
    ];

    /**
     * @var array|string[] list of fields from th DriveFile we are going to use
     */
    private array $fileDetailsToParse = [
        'id',
        'name',
        'mimeType',
    ];

    private Drive $_service;
    private Zend_Log $_logger;

    public function __construct()
    {
        $client = new Marktjagd_Service_Output_GoogleAuth();
        $this->_service = new Drive($client->getClient(Google_Service_Drive::DRIVE));
        $this->_logger = Zend_Registry::get('logger');
    }

    public function getFiles(string $url, string $type = ''): array
    {
        preg_match('#\/*([^\/?]+)(?:\?.*)?$#', $url, $folderId);

        $fileList = [];
        $pageToken = NULL;
        $parameters = [
            'q' => '"' . $folderId[1] . '" in parents'
        ];

        do {
            try {
                if ($pageToken) {

                    $parameters['pageToken'] = $pageToken;
                }

                $files = $this->_service->files->listFiles($parameters);

                foreach ($files->getFiles() as $driveFile) {
                    $parsed = $this->parseDriveFile($driveFile);

                    if ($parsed['mimeType'] == self::FOLDER_MIME_TYPE) {
                        $parsed['items'] = $this->getFiles($parsed['id'], $type);
                    }

                    $fileList[] = $parsed;
                }

                $pageToken = $files->getNextPageToken();
            }
            catch (Exception $e) {
                $this->_logger->err("Couldn't get files for folder '{$folderId[1]}': " . $e->getMessage());
                return [];
            }
        }
        while ($pageToken);

        if (!empty($type)) {
            $fileList = $this->filterFilesByType($fileList, $type);
        }

        return $fileList;
    }

    private function parseDriveFile(Drive\DriveFile $driveFile): array
    {
        $fileData = [];
        foreach ($this->fileDetailsToParse as $field) {
            if (property_exists($driveFile, $field)) {
                $fileData[$field] = $driveFile->$field;
            }
        }

        if ($fileData['mimeType'] == self::FOLDER_MIME_TYPE) {
            $fileData['type'] = 'folder';
        }
        else {
            if (isset(self::GOOGLE_MIME_TYPES_TO_REGULAR[$fileData['mimeType']])) {
                $regularMimeType = self::GOOGLE_MIME_TYPES_TO_REGULAR[$fileData['mimeType']];
                $fileData['type'] = self::MIME_TYPE_TO_EXT_MAP[$regularMimeType];
            }
            else {
                $fileData['type'] = self::MIME_TYPE_TO_EXT_MAP[$fileData['mimeType']];
            }
        }

        return $fileData;
    }

    public function filterFilesByType(array $fileList, string $type): array
    {
        $result = [];

        foreach ($fileList as $listItem) {
            if ($listItem['type'] == 'folder') {
                $result = array_merge($result, $this->filterFilesByType($listItem['items'], $type));
            }
            else {
                if ($listItem['type'] == $type) {
                    $result[] = $listItem;
                }
            }
        }

        return $result;
    }

    public function downloadFile(string $fileId, string $dirPath, string $fileNameOverride): string
    {
        try {
            $file = $this->_service->files->get($fileId);

            if (isset(self::GOOGLE_MIME_TYPES_TO_REGULAR[$file['mimeType']])) {
                // if it's Google file, we need to use export function
                $mimeType = self::GOOGLE_MIME_TYPES_TO_REGULAR[$file['mimeType']];
                $response = $this->_service->files->export($fileId, $mimeType);

                $fileName = $file['name'] . '.' . self::MIME_TYPE_TO_EXT_MAP[$mimeType];
            }
            else {
                $response = $this->_service->files->get($fileId, [
                    'alt' => 'media'
                ]);

                $fileName = $file['name'];
            }

            if ($fileNameOverride) {
                $fileName = $fileNameOverride;
            }

            file_put_contents($dirPath . $fileName, $response->getBody());
            return $dirPath . $fileName;
        }
        catch (Exception $e) {
            $this->_logger->err("Couldn't download file '{$fileId}': " . $e->getMessage());
            return false;
        }
    }

}
