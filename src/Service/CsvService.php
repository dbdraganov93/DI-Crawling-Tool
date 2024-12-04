<?php

namespace App\Service;

use League\Csv\Writer;
use SplTempFileObject;

class CsvService
{
    private string $csvDir;

    public function __construct(string $csvDir = './')
    {
        $this->csvDir = rtrim($csvDir, '/');
    }

    public function createCsvFromStores(StoreService $storeService): array
    {
        $stores = $storeService->getStores();

        if (empty($stores)) {
            throw new \RuntimeException('No stores to export.');
        }

        $headers = [
            'store_number', 'city', 'zipcode', 'street', 'street_number',
            'latitude', 'longitude', 'title', 'subtitle', 'text', 'phone',
            'fax', 'email', 'store_hours', 'store_hours_notes', 'payment',
            'website', 'distribution', 'parking', 'barrier_free', 'bonus_card',
            'section', 'service', 'toilet', 'default_radius'
        ];

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne($headers);

        foreach ($stores as $store) {
            $csv->insertOne(array_values($store));
        }

        $timestamp = round(microtime(true) * 1000);
        $fileName = sprintf('stores_%d_company_%d.csv', $timestamp, $storeService->getCompanyId());
        $filePath = $this->csvDir . './public/csv/' . $fileName;

        // Ensure the directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }

        file_put_contents($filePath, $csv->toString());
        $domain = getenv('APP_DOMAIN');
        return [
            'filePath' => $filePath,
            'message' => "CSV created successfully: {$fileName}. \n Download at http://127.0.0.1:8000/csv/{$fileName}",
            'downloadLink' => $domain . "http://127.0.0.1:8000/csv/{$fileName}",
        ];
    }
}
