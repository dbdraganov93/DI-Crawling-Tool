<?php

namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Service
{
    private S3Client $s3Client;
    private string $bucket;
    private string $region;

    public function __construct(string $bucket, string $region, ?string $profile = null)
{
    $this->bucket = $bucket;
    $this->region = $region;

    $options = [
        'region' => $this->region,
        'version' => 'latest',
        'use_aws_shared_config_files' => true,
    ];

    if ($profile) {
        $options['profile'] = $profile;
    }

    $this->s3Client = new S3Client($options);
}

    /**
     * Upload file to S3 and return public URL
     */
    public function upload(string $localPath): string
    {
        try {
            $timestamp = round(microtime(true) * 1000); // milliseconds
            $filename = basename($localPath);
            $s3Key = "pdf/{$timestamp}/{$filename}";

            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'SourceFile' => $localPath,
                // 'ACL' => 'public-read', // uncomment if you want a public URL
            ]);

            return "https://s3.{$this->region}.amazonaws.com/{$this->bucket}/{$s3Key}";
        } catch (AwsException $e) {
            throw new \RuntimeException("Upload failed: " . $e->getMessage());
        }
    }
}
