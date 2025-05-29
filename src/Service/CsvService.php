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
            $row = [
                $store['storeNumber'] ?? '',
                $store['city'] ?? '',
                $store['postalCode'] ?? '',
                $store['street'] ?? '',
                $store['street_number'] ?? '',
                $store['latitude'] ?? '',
                $store['longitude'] ?? '',
                $store['title'] ?? '',
                $store['subtitle'] ?? '',
                $store['text'] ?? '',
                $store['phone'] ?? '',
                $store['fax'] ?? '',
                $store['email'] ?? '',
                $store['store_hours'] ?? '',
                $store['store_hours_notes'] ?? '',
                $store['payment'] ?? '',
                $store['website'] ?? '',
                $store['distribution'] ?? '',
                $store['parking'] ?? '',
                $store['barrier_free'] ?? '',
                $store['bonus_card'] ?? '',
                $store['section'] ?? '',
                $store['service'] ?? '',
                $store['toilet'] ?? '',
                $store['default_radius'] ?? '',
            ];

            $csv->insertOne($row);
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
        $csvContent = $csv->toString();
        file_put_contents($filePath, $csvContent);
        $base64Csv = base64_encode($csvContent);

        $domain = getenv('APP_DOMAIN');

        return [
            'companyId' => $storeService->getCompanyId(),
            'type' => 'stores',
            'filePath' => $filePath,
            'message' => "CSV created successfully: {$fileName}. \n Download at http://127.0.0.1:8000/csv/{$fileName}",
            'downloadLink' => $domain . "http://127.0.0.1:8000/csv/{$fileName}",
            'base64' => $base64Csv,
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
                $brochure['brochureNumber'] ?? '',
                $brochure['type'] ?? 'default',
                $brochure['pdfUrl'] ?? '',
                $brochure['title'] ?? '',
                $brochure['tags'] ?? '', // tags
                $brochure['validFrom'] ?? '',
                $brochure['validTo'] ?? '',
                $brochure['visibleFrom'] ?? '',
                $brochure['storeNumber'] ?? '',
                $brochure['distribution'] ??'', // distribution
                $brochure['variety'] ?? '',
                $brochure['national'] ?? '', // national
                $brochure['gender'] ?? '',
                $brochure['ageRange'] ?? '',
                isset($brochure['tracking_pixels']) ? implode(',', $brochure['tracking_pixels']) : '',
                isset($brochure['pdf_processing_options']) ? json_encode($brochure['pdf_processing_options']) : '',
                '', // lang_code
                '', // zipcode
                $brochure['layout'] ?? '',
            ]);
        }

        $timestamp = round(microtime(true) * 1000);
        $fileName = sprintf('brochures_%d_company_%d.csv', $companyId, $timestamp);
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

        $csvContent = $csv->toString();
        file_put_contents($filePath, $csvContent);
        $base64Csv = base64_encode($csvContent);

        $domain = getenv('APP_DOMAIN');

        return [
            'companyId' => $companyId,
            'type' => 'brochures',
            'filePath' => $filePath,
            'message' => "CSV created successfully: {$fileName}. \n Download at http://127.0.0.1:8000/csv/{$fileName}",
            'downloadLink' => $domain . "http://127.0.0.1:8000/csv/{$fileName}",
            'base64' => $base64Csv,
        ];
    }

}
