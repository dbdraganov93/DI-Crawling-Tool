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

    public function createCsvFromBrochure(BrochureService $brochureService): array
    {
        $brochures = $brochureService->getBrochures(); // ← THIS WAS MISSING

        if (empty($brochures)) {
            throw new \RuntimeException('No brochures to export.');
        }
        $companyId = $brochureService->getCompanyId();

        $headers = [
            'brochure_number',
            'type',
            'url',
            'title',
            'tags',
            'start',
            'end',
            'visible_start',
            'store_number',
            'distribution',
            'variety',
            'national',
            'gender',
            'age_range',
            'tracking_bug',
            'options',
            'lang_code',
            'zipcode',
            'layout',
        ];

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne($headers);

        foreach ($brochures as $brochure) {
            $csv->insertOne([
                $brochure['brochure_number'] ?? '',
                $brochure['integration'] ?? '',
                $brochure['pdf_url'] ?? '',
                $brochure['title'] ?? '',
                '', // tags
                $brochure['valid_from'] ?? '',
                $brochure['valid_to'] ?? '',
                $brochure['visible_from'] ?? '',
                $brochure['sales_region'] ?? '',
                '', // distribution
                $brochure['variety'] ?? '',
                '', // national
                $brochure['gender'] ?? '',
                $brochure['age_range'] ?? '',
                isset($brochure['tracking_pixels']) ? implode(',', $brochure['tracking_pixels']) : '',
                isset($brochure['pdf_processing_options']) ? json_encode($brochure['pdf_processing_options']) : '',
                '', // lang_code
                '', // zipcode
                $brochure['layout'] ?? '',
            ]);
        }

        $timestamp = round(microtime(true) * 1000);
        $fileName = sprintf('brochures_%d_company_%d.csv', $timestamp, $companyId);
        $filePath = $this->csvDir . './public/csv/' . $fileName;

        // Ensure directory exists
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
