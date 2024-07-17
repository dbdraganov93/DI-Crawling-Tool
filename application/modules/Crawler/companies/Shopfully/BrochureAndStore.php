<?php

class Crawler_Company_Shopfully_BrochureAndStore extends Crawler_Generic_Company
{
    private const SPREADSHEET_ID = '17aLeUzXmYNGvMb6RkAMxV5HMaoYns4-pxM-4VYW0OrE';
    private const DATE_FORMAT = 'Y-m-d H:i:s e';
    private Shopfully_Service_BrochureApi $shopfullyBrochureApi;
    private Shopfully_Service_StoreApi $shopfullyStoreApi;
    private Shopfully_Service_RetailerApi $shopfullyRetailerApi;
    private Marktjagd_Service_Input_MarktjagdApi $iProtoApi;
    private array $skippedCompanies;
    private array $brochuresImagesBatchIds;
    protected array $companiesMap = [];
    private array $companies = [
        '81694' => [
            'ownerId' => '231',
            'languageCode' => 'it',
            'shopfullyLanguageCode' => 'it_it',
        ],
    ];

    public function __construct()
    {
        $this->iProtoApi = new Marktjagd_Service_Input_MarktjagdApi();
    }

    public function crawl($companyId)
    {
        $this->ownerInfo = $this->companies[$companyId] ?? null;
        if (null === $this->ownerInfo) {
            throw new Exception(sprintf('Company %s not found', $companyId));
        }

        $this->skippedCompanies = $this->getListOfCompaniesForSkipp();
        $this->shopfullyBrochureApi = new Shopfully_Service_BrochureApi($this->ownerInfo['shopfullyLanguageCode']);
        $this->shopfullyStoreApi = new Shopfully_Service_StoreApi($this->ownerInfo['shopfullyLanguageCode']);
        $this->shopfullyRetailerApi = new Shopfully_Service_RetailerApi($this->ownerInfo['shopfullyLanguageCode']);

        $brochures = $this->getBrochuresFromShopfully();

        $this->brochuresImagesBatchIds = $this->createBrochuresImagesBatches($brochures);

        foreach ($brochures as $brochure) {
            // Check if the brochure stores are in our system and create them if not.
            $this->checkIfStoresExist($brochure);
            $this->createBrochure($brochure);
        }

        return $this->getSuccessResponse();
    }

    /**
     * Get the brochures from Shopfully and check if we have the company in our system.
     */
    private function getBrochuresFromShopfully(): array
    {
        $brochures = $this->shopfullyBrochureApi->getBrochuresThatStartToday();
        foreach ($brochures as $index => $brochure) {
            if ($this->skippBrochure($brochure)) {
                unset($brochures[$index]);
            }
        }

        return $brochures;
    }

    /**
     * Create a batch request for the brochure images.
     * The images will be created in the background process.
     * On prod, we can run parallel 250 batches in AWS.
     */
    private function createBrochuresImagesBatches(array $brochures): array
    {
        $brochuresImagesBatchIds = [];
        foreach ($brochures as $brochure) {
            $imageUrls = $brochure->getImages();
            $bacheId = $this->iProtoApi->createBrochureImagesBatch($imageUrls);
            $brochuresImagesBatchIds[$brochure->getId()] = $bacheId;
        }

        return $brochuresImagesBatchIds;
    }

    /**
     * Check if the company exists in our system.
     */
    private function findCompany(int $retailerId): ?array
    {
        // Check if we already mapped this company (We have already searched for this company).
        if (!isset($this->companiesMap[$retailerId])) {
            // Get the retailer data from Shopfully.
            $retailer = $this->shopfullyRetailerApi->getRetailerById($retailerId);

            // Search for the company in our system by retailer name.
            $company = $this->iProtoApi->findCompanyByName($this->ownerInfo['ownerId'], $retailer->getName());
            // Save the company in the mapped companies. Put null if the company is not found to not serach again.
            $this->companiesMap[$retailerId] = $company?: null;
        } else {
            // Get the company from the mapped companies.
            $company = $this->companiesMap[$retailerId];
        }

        return $company;
    }

    /**
     * This function skipp the brochure if:
     * - checks if the brochure has images
     * - checks if the company exists in our system
     * - checks if the brochure are already in our system
     * - checks if the company is setup to be skipped from the crawler
     */
    private function skippBrochure(Shopfully_Entity_Brochure $brochure): bool
    {
        // Check if the company is setup to be skipped from the crawler.
        $skipped = array_filter($this->skippedCompanies, function ($company) use ($brochure) {
            return $company['shopfullyCompanyId'] == $brochure->getRetailerId();
        });

        if (!empty($skipped)) {
            return true;
        }

        // Search for the company in our system.
        $company = $this->findCompany($brochure->getRetailerId());
        $companyId = $company['id'] ?? null;

        // Check if the brochure has images, if not we can't create it.
        // Check if the company exists in our system. We putt null if the company is not found to not search again.
        if ($brochure->getImages() == null || $companyId == null) {
            return true;
        }

        // Check if the brochure is already in our system.
        $brochureData = $this->iProtoApi->findBrochureByBrochureNumberAndCompany($brochure->getId(), $companyId);
        if ($brochureData) {
            return true;
        }

        return false;
    }

    /**
     * Check if the stores from the brochure are in our system.
     * If not, create them.
     */
    private function checkIfStoresExist(Shopfully_Entity_Brochure $brochures): void
    {
        $companyId = $this->companiesMap[$brochures->getRetailerId()]['id'];
        $stores = ($this->iProtoApi->findStoresByCompany($companyId))->getElements();

        foreach ($brochures->getStores() as $shopfullyStore) {
            $storeExists = array_filter($stores, function ($store) use ($shopfullyStore) {
                return $store->getStoreNumber() == $shopfullyStore;
            });

            if (empty($storeExists)) {
                $this->createStore($shopfullyStore, $companyId);
            }
        }
    }

    /**
     * Create a store in our system.
     */
    private function createStore(int $storeNumber, int $companyId): void
    {
        $storeData = $this->shopfullyStoreApi->getStoreById($storeNumber);
        $store = [
            'integration' => '/api/integrations/' . $companyId,
            'storeNumber' => (string)$storeData->getId(),
            'street' => $storeData->getAddress(),
            'postalCode' => $storeData->getZip(),
            'city' => $storeData->getCity(),
            'latitude' => (float)$storeData->getLat(),
            'longitude' => (float)$storeData->getLng()
        ];

        try {
            $this->iProtoApi->createStore($store);
        } catch (Exception $e) {
            $this->_logger->err(sprintf('Error creating store: %s', $storeData->getId()));
        }
    }

    /**
     * Get the list of companies that are setup to be skipped from the crawler.
     */
    private function getListOfCompaniesForSkipp(): array
    {
        $googleSheetsService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        return $googleSheetsService->getFormattedInfos(self::SPREADSHEET_ID, 'A1', 'B', $this->ownerInfo['languageCode']);
    }

    /**
     * Create a sales region for the company stores.
     */
    private function createSalesRegion(int $companyId, array $stores): ?int
    {
        try {
            $salesRegion = $this->iProtoApi->createSalesRegionFromStoreNumbers($companyId, $stores);
        } catch (Exception $e) {
            $this->_logger->err(sprintf('Error creating sales region %s', $e->getMessage()));
            return null;
        }

        return $salesRegion['id'];
    }

    /**
     * Get the images from the batch.
     */
    private function getImagesFromBatch(Shopfully_Entity_Brochure $brochureData): ?array
    {
        $batchId = $this->brochuresImagesBatchIds[$brochureData->getId()];
        $imagesBatch = $this->iProtoApi->getBrochureImagesByBatchId($batchId);
        $page = 0;
        $images = [];
        foreach ($imagesBatch as $imageId) {
            $images[$page] = [
                "position" => $page,
                "image" => $imageId,
            ];
            $page++;
        }

        return $images;
    }

    /**
     * Create a brochure in our system.
     */
    private function createBrochure(Shopfully_Entity_Brochure $brochureData): void
    {
        $companyId = $this->companiesMap[$brochureData->getRetailerId()]['id'];
        $salesRegionId = $this->createSalesRegion($companyId, $brochureData->getStores());

        $images = $this->getImagesFromBatch($brochureData);

        $brochure = [
            'brochureNumber' => (string) $brochureData->getId(),
            'integration' => '/api/integrations/' . $companyId,
            'title' => $brochureData->getTitle(),
            'validFrom' => ($brochureData->getStartDate())->format(self::DATE_FORMAT),
            'validTo' => ($brochureData->getEndDate())->format(self::DATE_FORMAT),
            'visibleFrom' => ($brochureData->getStartDate())->format(self::DATE_FORMAT),
            'languageCode' => $this->ownerInfo['languageCode'],
            'salesRegion' => $salesRegionId ? '/api/sales_regions/' . $salesRegionId : null,
            'type' => 'default',
            'pages' => $images,
        ];

        try {
            $this->iProtoApi->createBrochure($brochure);
        } catch (Exception $e) {
            $this->_logger->err(sprintf('Error creating brochure %s', $e->getMessage()));
        }
    }
}
