<?php

declare(strict_types=1);

namespace App\Service;

use League\Csv\Writer;
use SplTempFileObject;
use App\Dto\Brochure;

class CsvService
{
    private string $csvDir;

    public function __construct(string $csvDir = './public/csv')
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
        $fileName = sprintf('stores_%d_%d.csv', $timestamp, $storeService->getCompanyId());
        // $csvDir already points to the CSV directory, just append the filename
        $filePath = rtrim($this->csvDir, '/') . '/' . $fileName;

        // Ensure the directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }

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

    public function createCsvFromBrochure(array $brochures, string $companyId): array
    {
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
            if (!$brochure instanceof Brochure) {
                throw new \InvalidArgumentException('Expected array of Dto\Brochure objects.');
            }

            $csv->insertOne([
                $brochure->getBrochureNumber() ?? '',
                $brochure->getType() ?? '',
                $brochure->getPdfUrl() ?? '',
                $brochure->getTitle() ?? '',
                '', // tags
                $brochure->getValidFrom() ?? '',
                $brochure->getValidTo() ?? '',
                $brochure->getVisibleFrom() ?? '',
                $brochure->getStoreNumber() ?? '',
                $brochure->getSalesRegion() ?? '',
                $brochure->getVariety() ?? '',
                '', // national
                '', // gender
                '', // ageRange
                $brochure->getTrackingPixels() ?? '',
                !empty($brochure->getPdfProcessingOptions()) ? json_encode($brochure->getPdfProcessingOptions()) : '',
                '', // lang_code
                $brochure->getZipcode() ?? '', // zipcode
                $brochure->getLayout() ?? '',
            ]);
        }

        $timestamp = round(microtime(true) * 1000);
        $fileName = sprintf('brochures_%d_%d.csv', $companyId, $timestamp);
        // $csvDir already points to the CSV directory, just append the filename
        $filePath = rtrim($this->csvDir, '/') . '/' . $fileName;

        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }

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
