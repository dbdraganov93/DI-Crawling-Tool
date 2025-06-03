<?php
namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Service {
    private S3Client $s3Client;
    private string $bucket;
    private string $region;

    public function __construct(string $bucket, string $region, string $profile)
    {
        $this->bucket = $bucket;
        $this->region = $region;

        $this->s3Client = new S3Client([
            'region' => $this->region,
            'version' => 'latest',
            'profile' => $profile,
            'use_aws_shared_config_files' => true,
        ]);
    }

    /**
     * Upload file to S3 and return public URL
     */
    public function upload(string $localPath, string $s3Path): string
    {
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Path,
                'SourceFile' => $localPath,
                //'ACL' => 'public-read',
            ]);

            return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$s3Path}";
        } catch (AwsException $e) {
            throw new \RuntimeException("Upload failed: " . $e->getMessage());
        }
    }
}