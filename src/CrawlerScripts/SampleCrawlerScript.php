<?php

namespace App\CrawlerScripts;

use App\Service\CsvService;
use App\Service\StoreService;

class SampleCrawlerScript implements CrawlerScriptInterface
{


    public function crawl(int $companyId): string
    {
        // Initialize StoreService
        $storeService = new StoreService($companyId);
        $csvService = new CsvService();

        // Example stores array
        $stores = [
            [
                'store_number' => '12345',
                'city' => 'Berlin',
                'zipcode' => '10115',
                'street' => 'Example Street',
                'street_number' => '42',
                'latitude' => '52.5200',
                'longitude' => '13.4050',
                'title' => 'Sample Store',
                'subtitle' => 'Best Store in Town',
                'text' => 'A sample description.',
                'phone' => '123-456-7890',
                'fax' => '123-456-7891',
                'email' => 'sample@store.com',
                'store_hours' => '8 AM - 10 PM',
                'store_hours_notes' => 'Closed on public holidays',
                'payment' => 'Cash, Card',
                'website' => 'https://www.samplestore.com',
                'distribution' => 'Local',
                'parking' => 'Available',
                'barrier_free' => 'Yes',
                'bonus_card' => 'Available',
                'section' => 'Electronics',
                'service' => 'Customer Support',
                'toilet' => 'Available',
                'default_radius' => '5km',
            ],
            [
                'store_number' => '67890',
                'city' => 'Hamburg',
                'zipcode' => '20095',
                'street' => 'Another Street',
                'street_number' => '50',
                'latitude' => '53.5511',
                'longitude' => '9.9937',
                'title' => 'Another Store',
                'subtitle' => 'Popular Store',
                'text' => 'Another description.',
                'phone' => '987-654-3210',
                'fax' => '987-654-3211',
                'email' => 'another@store.com',
                'store_hours' => '9 AM - 8 PM',
                'store_hours_notes' => 'Closed on Sundays',
                'payment' => 'Card only',
                'website' => 'https://www.anotherstore.com',
                'distribution' => 'Regional',
                'parking' => 'Limited',
                'barrier_free' => 'No',
                'bonus_card' => 'Not available',
                'section' => 'Clothing',
                'service' => 'Customer Care',
                'toilet' => 'Not available',
                'default_radius' => '10km',
            ],
        ];

        // Add each store to the StoreService
        foreach ($stores as $storeData) {
            $storeService
                ->setStoreNumber($storeData['store_number'])
                ->setCity($storeData['city'])
                ->setZipcode($storeData['zipcode'])
                ->setStreet($storeData['street'])
                ->setStreetNumber($storeData['street_number'])
                ->setLatitude($storeData['latitude'])
                ->setLongitude($storeData['longitude'])
                ->setTitle($storeData['title'])
                ->setSubtitle($storeData['subtitle'])
                ->setText($storeData['text'])
                ->setPhone($storeData['phone'])
                ->setFax($storeData['fax'])
                ->setEmail($storeData['email'])
                ->setStoreHours($storeData['store_hours'])
                ->setStoreHoursNotes($storeData['store_hours_notes'])
                ->setPayment($storeData['payment'])
                ->setWebsite($storeData['website'])
                ->setDistribution($storeData['distribution'])
                ->setParking($storeData['parking'])
                ->setBarrierFree($storeData['barrier_free'])
                ->setBonusCard($storeData['bonus_card'])
                ->setSection($storeData['section'])
                ->setService($storeData['service'])
                ->setToilet($storeData['toilet'])
                ->setDefaultRadius($storeData['default_radius'])
                ->addCurrentStore();
        }

        // Generate CSV
        $filePath = $csvService->createCsvFromStores($storeService);

        echo "CSV created at: {$filePath}\n";

        return $filePath;
    }
}
