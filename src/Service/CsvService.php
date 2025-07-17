<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Store;
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

    public function createCsvFromStores(array $stores, string $companyId): array
    {

        if (empty($stores)) {
            throw new \RuntimeException('No stores to export.');
        }

        $headers = [
            'store_number',
            'city',
            'zipcode',
            'street',
            'street_number',
            'latitude',
            'longitude',
            'title',
            'subtitle',
            'text',
            'phone',
            'fax',
            'email',
            'store_hours',
            'store_hours_notes',
            'payment',
            'website',
            'distribution',
            'parking',
            'barrier_free',
            'bonus_card',
            'section',
            'service',
            'toilet',
            'default_radius'
        ];

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne($headers);

        $storeNumbers = [];
        foreach ($stores as $store) {
            if (!$store instanceof Store) {
                throw new \InvalidArgumentException('Expected array of Dto\Store objects.');
            }

            $storeNumber = $store->getStoreNumber();
            if (in_array($storeNumber, $storeNumbers)) {
                continue; // Skip duplicate store numbers
            }

            $storeNumbers[] = $storeNumber;

            $row = [
                $storeNumber,
                $store->getCity() ?? '',
                $store->getZipcode() ?? '',
                $store->getStreet() ?? '',
                $store->getStreetNumber() ?? '',
                $store->getLatitude() ?? '',
                $store->getLongitude() ?? '',
                $store->getTitle() ?? '',
                $store->getSubtitle() ?? '',
                $store->getText() ?? '',
                $store->getPhone() ?? '',
                $store->getFax() ?? '',
                $store->getEmail() ?? '',
                $store->getStoreHours() ?? '',
                $store->getStoreHoursNotes() ?? '',
                $store->getPayment() ?? '',
                $store->getWebsite() ?? '',
                $store->getDistribution() ?? '',
                $store->getParking() ?? '',
                $store->getBarrierFree() ?? '',
                $store->getBonusCard() ?? '',
                $store->getSection() ?? '',
                $store->getService() ?? '',
                $store->getToilet() ?? '',
                $store->getDefaultRadius() ?? '',
            ];

            $csv->insertOne($row);
        }


        $timestamp = round(microtime(true) * 1000);
        $fileName = sprintf('stores_%d_%d.csv', $timestamp, $companyId);
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
        dd($csvContent);
        file_put_contents($filePath, $csvContent);
        $base64Csv = base64_encode($csvContent);
        $domain = getenv('APP_DOMAIN');

        return [
            'companyId' => $companyId,
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
                $brochure->getTags() ?? '',
                $brochure->getValidFrom() ?? '',
                $brochure->getValidTo() ?? '',
                $brochure->getVisibleFrom() ?? '',
                $brochure->getStoreNumber() ?? '',
                $brochure->getSalesRegion() ?? '',
                $brochure->getVariety() ?? '',
                $brochure->getNational() ?? '', // leave empty if it's not set
                $brochure->getGender() ?? '',
                $brochure->getAgeRange() ?? '',
                $brochure->getTrackingPixels() ?? '',
                !empty($brochure->getPdfProcessingOptions()) ? json_encode($brochure->getPdfProcessingOptions()) : '',
                $brochure->getLangCode() ?? '',
                $brochure->getZipcode() ?? '',
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
