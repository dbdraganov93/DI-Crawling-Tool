<?php

declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Service
{
    private S3Client $s3Client;
    private string $bucket;
    private string $region;

    public function __construct(
        string $bucket,
        string $region,
        ?string $profile = null,
        ?string $accessKey = null,
        ?string $secretKey = null
    ) {
        $this->bucket = $bucket;
        $this->region = $region;

        $config = [
            'region' => $this->region,
            'version' => 'latest',
        ];

        if ($profile && !getenv('AWS_CONTAINER_CREDENTIALS_RELATIVE_URI')) {
            $config['profile'] = $profile;
            $config['use_aws_shared_config_files'] = true;
        }

        $accessKey = $this->resolveCredential($accessKey, 'AWS_ACCESS_KEY_ID');
        $secretKey = $this->resolveCredential($secretKey, 'AWS_SECRET_ACCESS_KEY');

        if ($accessKey && $secretKey) {
            $config['credentials'] = [
                'key' => $accessKey,
                'secret' => $secretKey,
            ];
        }

        $this->s3Client = new S3Client($config);
    }

    private function resolveCredential(?string $value, string $envKey): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        $envValue = getenv($envKey);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        if (isset($_ENV[$envKey]) && $_ENV[$envKey] !== '') {
            return $_ENV[$envKey];
        }

        if (isset($_SERVER[$envKey]) && $_SERVER[$envKey] !== '') {
            return $_SERVER[$envKey];
        }

        return null;
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
