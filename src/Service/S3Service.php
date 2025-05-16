<?php
namespace App\Service;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class S3Service
{
    private S3Client $s3Client;
    private string $bucket;

    public function __construct(string $awsKey, string $awsSecret, string $region, string $bucket)
    {
        $this->bucket = $bucket;

        $this->s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $awsKey,
                'secret' => $awsSecret,
            ],
        ]);
    }

    /**
     * Upload a file to S3 and return the public URL.
     *
     * @param string $key      The S3 object key (path/filename)
     * @param string $filePath Local path to the file
     * @param string $mimeType MIME type of the file
     *
     * @return string|null     Public URL of uploaded file, or null on failure
     */
    public function upload(string $key, string $filePath, string $mimeType): ?string
    {
        try {
            $result = $this->s3Client->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $key,
                'SourceFile'  => $filePath,
                'ContentType' => $mimeType,
                'ACL'         => 'public-read',
            ]);

            return $result->get('ObjectURL');
        } catch (S3Exception $e) {
            // You can log the error here if needed
            return null;
        }
    }
}
