<?php

/*
 * Helper methods for Ep At (ID: 72750)
 */
class Crawler_Company_EpAt_DiscoverHelpers
{
    private const FTP_CONFIG = [
        'hostname' => 'filecenter.electronicpartner.com',
        'username' => 'ex_at_wogibtswas',
        'password' => '25Kn565i',
        'port' => '21'
    ];
    private const CENTRAL_EP_STORE = '1201950';

    public function downloadZipsFromClientFTP(int $companyId): array
    {
        $ftp = new Marktjagd_Service_Transfer_Ftp();

        $ftp->connect(self::FTP_CONFIG);
        $localPath = $ftp->generateLocalDownloadFolder($companyId);

        # download the archive
        $files = [];
        foreach ($ftp->listFiles() as $singleFile) {
            $pattern = '#\.zip$#';
            if (preg_match($pattern, $singleFile)) {
                $files[] = $ftp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        $ftp->close();

        if (empty($files)) {
            throw new Exception('Company ID: ' . $companyId . ': no Discover data found on client FTP server.');
        }

        return $files;
    }

    public function unzipFile(string $zipFilePath, int $companyId): string
    {
        $archiveService = new Marktjagd_Service_Input_Archive();

        preg_match('#(.*)\.zip$#', $zipFilePath, $extractPathMatch);
        $extractPath = $extractPathMatch[1];
        if (!$archiveService->unzip($zipFilePath, $extractPath)) {
            throw new Exception('Company ID: ' . $companyId . ': unable to unzip archive.');
        }

        return $extractPath;
    }

    public function getCentralEPStore(): string
    {
        return self::CENTRAL_EP_STORE;
    }

    public function isCentralEPStore(string $storeNumber): bool
    {
        return $storeNumber === self::CENTRAL_EP_STORE;
    }
}