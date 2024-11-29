<?php
namespace App\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;

class FtpService
{
    private Filesystem $filesystem;

    public function __construct(string $ftpServer, string $ftpUsername, string $ftpPassword, int $ftpPort = 21)
    {
        $connectionOptions = FtpConnectionOptions::fromArray([
            'host' => $ftpServer,
            'username' => $ftpUsername,
            'password' => $ftpPassword,
            'port' => $ftpPort,
            'passive' => true, // Enable passive mode
        ]);

        $adapter = new FtpAdapter($connectionOptions);
        $this->filesystem = new Filesystem($adapter);
    }

    public function listFiles(string $directory = ''): array
    {
        $listing = $this->filesystem->listContents($directory);

        // Convert DirectoryListing to array
        $files = [];
        foreach ($listing as $item) {
            $files[] = [
                'path' => $item->path(),
                'type' => $item->isFile() ? 'file' : 'directory',
            ];
        }

        return $files;
    }

    public function uploadFile(string $localPath, string $remotePath): void
    {
        $contents = file_get_contents($localPath);
        $this->filesystem->write($remotePath, $contents);
    }

    public function downloadFile(string $remotePath, string $localPath): void
    {
        $contents = $this->filesystem->read($remotePath);
        file_put_contents($localPath, $contents);
    }

    public function deleteFile(string $remotePath): void
    {
        $this->filesystem->delete($remotePath);
    }
}
