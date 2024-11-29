<?php

namespace App\Service;

use League\Csv\Writer;
use SplTempFileObject;

class CsvService
{
    public function __construct($csvDir = './')
    {
        // Constructor logic (if needed)
    }

    public function createCsvFromStores(StoreService $storeService): string
    {
        // Retrieve the data for all stores
        $stores = $storeService->getStores();

        if (empty($stores)) {
            throw new \RuntimeException('No stores to export.');
        }

        // Define the CSV headers
        $headers = [
            'store_number', 'city', 'zipcode', 'street', 'street_number',
            'latitude', 'longitude', 'title', 'subtitle', 'text', 'phone',
            'fax', 'email', 'store_hours', 'store_hours_notes', 'payment',
            'website', 'distribution', 'parking', 'barrier_free', 'bonus_card',
            'section', 'service', 'toilet', 'default_radius'
        ];

        // Create the CSV writer
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne($headers);

        // Insert all store rows
        foreach ($stores as $store) {
            $csv->insertOne(array_values($store));
        }

        // Generate a timestamp in milliseconds
        $timestamp = round(microtime(true) * 1000);

        // Define the file path with the prefix "stores_" and the timestamp
        $filePath = sprintf(
            '%s/public/csv/stores_%d_%d.csv',
            __DIR__ . '/../../',
            $storeService->getCompanyId(),
            $timestamp
        );

        // Save the CSV file
        file_put_contents($filePath, $csv->toString());

        return $filePath;
    }
}
