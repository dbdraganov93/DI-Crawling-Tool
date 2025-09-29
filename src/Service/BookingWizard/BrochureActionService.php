<?php

declare(strict_types=1);

namespace App\Service\BookingWizard;

use App\Dto\Brochure;
use App\Dto\Product;
use App\Service\CsvService;
use App\Service\IprotoService;
use App\Service\S3Service;
use InvalidArgumentException;

class BrochureActionService
{
    public const ACTION_DUPLICATE_PER_STORE = 'duplicate_per_store';
    public const ACTION_DUPLICATE_SELECTED_STORES = 'duplicate_selected_stores';

    /** @var array<string, string> */
    private array $brochurePdfCache = [];

    private bool $brochurePdfDirectoryEnsured = false;

    public function __construct(
        private IprotoService $iprotoService,
        private CsvService $csvService,
        private S3Service $s3Service,
        private string $brochurePdfDir = 'public/pdf',
    ) {
        $this->brochurePdfDir = rtrim($brochurePdfDir, '/');
    }

    /**
     * @param array<int, int|string> $brochureIds
     *
     * @return array{
     *     summary: array{selectedBrochures: int, stores: int, generated: int},
     *     csv: array<string, mixed>,
     *     import: array<string, mixed>,
     *     importId: string|null,
     * }
     */
    public function duplicateBrochuresPerStore(string $companyId, string $ownerId, array $brochureIds): array
    {
        return $this->duplicateBrochuresInternal($companyId, $ownerId, $brochureIds, null);
    }

    /**
     * @param array<int, int|string> $brochureIds
     * @param array<int, int|string> $storeNumbers
     *
     * @return array{
     *     summary: array{selectedBrochures: int, stores: int, generated: int},
     *     csv: array<string, mixed>,
     *     import: array<string, mixed>,
     *     importId: string|null,
     * }
     */
    public function duplicateBrochuresPerSelectedStores(string $companyId, string $ownerId, array $brochureIds, array $storeNumbers): array
    {
        return $this->duplicateBrochuresInternal($companyId, $ownerId, $brochureIds, $storeNumbers);
    }

    /**
     * @param array<int, int|string> $brochureIds
     * @param array<int, int|string>|null $storeNumbers
     *
     * @return array{
     *     summary: array{selectedBrochures: int, stores: int, generated: int},
     *     csv: array<string, mixed>,
     *     import: array<string, mixed>,
     *     importId: string|null,
     * }
     */
    private function duplicateBrochuresInternal(string $companyId, string $ownerId, array $brochureIds, ?array $storeNumbers): array
    {
        $normalizedCompanyId = $this->normalizeIdentifier($companyId);
        $normalizedOwnerId = $this->normalizeIdentifier($ownerId);
        $normalizedBrochureIds = $this->normalizeIdentifiers($brochureIds);
        $normalizedStoreNumbers = $storeNumbers === null ? null : $this->normalizeIdentifiers($storeNumbers);

        if ($normalizedCompanyId === '') {
            throw new InvalidArgumentException('A company must be selected to duplicate brochures.');
        }

        if ($normalizedOwnerId === '') {
            throw new InvalidArgumentException('An owner must be selected to duplicate brochures.');
        }

        if ($normalizedBrochureIds === []) {
            throw new InvalidArgumentException('Select at least one brochure to duplicate.');
        }

        if ($normalizedStoreNumbers !== null && $normalizedStoreNumbers === []) {
            throw new InvalidArgumentException('Select at least one store to duplicate brochures.');
        }

        $stores = $this->iprotoService->getStoresByCompany($normalizedCompanyId);
        $availableStoreNumbers = $this->extractStoreNumbers($stores);

        if ($availableStoreNumbers === []) {
            throw new \RuntimeException('No stores found for the selected company.');
        }

        $targetStoreNumbers = $availableStoreNumbers;

        if ($normalizedStoreNumbers !== null) {
            $reconciled = $this->reconcileSelectedStoreNumbers($normalizedStoreNumbers, $availableStoreNumbers);
            $targetStoreNumbers = $reconciled['stores'];

            if ($targetStoreNumbers === []) {
                if ($reconciled['missing'] !== []) {
                    throw new InvalidArgumentException(sprintf(
                        'The following stores are not available for the selected company: %s.',
                        implode(', ', $reconciled['missing'])
                    ));
                }

                throw new InvalidArgumentException('Select at least one store to duplicate brochures.');
            }

            if ($reconciled['missing'] !== []) {
                throw new InvalidArgumentException(sprintf(
                    'The following stores are not available for the selected company: %s.',
                    implode(', ', $reconciled['missing'])
                ));
            }
        }

        $brochureMetadata = [];
        $discoverLayouts = [];
        $discoverProductsByBrochure = [];
        $discoverProductDetails = [];

        foreach ($normalizedBrochureIds as $brochureId) {
            $brochureDetail = $this->iprotoService->getBrochureDetails($brochureId);
            $basePayload = $this->normalizeBrochurePayload($brochureDetail, $normalizedCompanyId, $brochureId);
            $basePayload['pdfUrl'] = $this->preparePdfUrl($basePayload['pdfUrl'], $brochureId);

            $brochureMetadata[$brochureId] = [
                'payload' => $basePayload,
            ];

            if (!$this->isDiscoverBrochure($basePayload)) {
                continue;
            }

            $layoutData = $this->decodeDiscoverLayout($brochureDetail);
            if ($layoutData === null) {
                continue;
            }

            $discoverLayouts[$brochureId] = $layoutData;
            $productIds = $this->collectDiscoverProductIds($layoutData);

            if ($productIds === []) {
                continue;
            }

            $discoverProductsByBrochure[$brochureId] = $productIds;

            foreach ($productIds as $productId) {
                if (isset($discoverProductDetails[$productId])) {
                    continue;
                }

                $productDetail = $this->iprotoService->getProduct($productId);
                $productNumber = $this->normalizeScalar($productDetail['productNumber'] ?? $productDetail['product_number'] ?? '');

                if ($productNumber === '') {
                    throw new \RuntimeException(sprintf(
                        'Product %s referenced by brochure %s is missing a product number.',
                        $productId,
                        $brochureId
                    ));
                }

                $discoverProductDetails[$productId] = [
                    'number' => $productNumber,
                    'data' => $productDetail,
                ];
            }
        }

        $productArticleLookup = [];
        $productIdLookup = [];

        if ($discoverProductDetails !== []) {
            $productPreparation = $this->prepareDiscoverProductsForStores(
                $discoverProductDetails,
                $discoverProductsByBrochure,
                $targetStoreNumbers,
                $normalizedCompanyId,
            );

            $productArticleLookup = $productPreparation['lookup'];

            if ($productPreparation['products'] !== []) {
                try {
                    $productCsv = $this->csvService->createCsvFromProducts($productPreparation['products'], $normalizedCompanyId);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException('Failed to create product CSV for brochure duplication.', 0, $exception);
                }

                try {
                    $this->iprotoService->importData($productCsv);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException('Failed to import duplicated products.', 0, $exception);
                }

                $productRecords = $this->waitForDuplicatedProducts($normalizedCompanyId, $productPreparation['articleNumbers']);
                $productIdLookup = $this->mapProductIdsByArticle($productRecords);
            }
        }

        $duplicatedBrochures = [];

        foreach ($normalizedBrochureIds as $brochureId) {
            $metadata = $brochureMetadata[$brochureId] ?? null;

            if ($metadata === null) {
                continue;
            }

            $basePayload = $metadata['payload'];
            $layoutData = $discoverLayouts[$brochureId] ?? null;

            foreach ($targetStoreNumbers as $storeNumber) {
                $payload = $basePayload;
                $payload['brochureNumber'] = $this->buildBrochureNumberForStore($basePayload['brochureNumber'], $storeNumber);
                $payload['storeNumber'] = $storeNumber;

                if ($layoutData !== null) {
                    $productIdMap = $this->buildProductIdMapForStore($productArticleLookup, $productIdLookup, $brochureId, $storeNumber);

                    if ($productIdMap !== []) {
                        $updatedLayout = $this->buildDiscoverLayoutForStore($layoutData, $productIdMap);

                        if ($updatedLayout !== null) {
                            $payload['layout'] = $updatedLayout;
                        }
                    }
                }

                try {
                    $duplicatedBrochures[] = Brochure::fromArray($payload);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException(
                        sprintf('Failed to duplicate brochure %s for store %s.', $brochureId, $storeNumber),
                        0,
                        $exception
                    );
                }
            }
        }

        if ($duplicatedBrochures === []) {
            throw new \RuntimeException('No brochures were duplicated.');
        }

        try {
            $brochureCsv = $this->csvService->createCsvFromBrochure($duplicatedBrochures, $normalizedCompanyId);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Failed to create brochure CSV for duplication.', 0, $exception);
        }

        try {
            $import = $this->iprotoService->importData($brochureCsv);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Failed to import duplicated brochures.', 0, $exception);
        }

        return [
            'summary' => [
                'selectedBrochures' => count($normalizedBrochureIds),
                'stores' => count($targetStoreNumbers),
                'generated' => count($duplicatedBrochures),
            ],
            'csv' => $brochureCsv,
            'import' => $import,
            'importId' => $this->extractImportId($import),
        ];
    }

    /**
     * @param array<int, mixed> $stores
     *
     * @return array<int, string>
     */
    private function extractStoreNumbers(array $stores): array
    {
        $storeNumbers = [];

        foreach ($stores as $store) {
            if (!is_array($store)) {
                continue;
            }

            $number = $store['storeNumber'] ?? $store['store_number'] ?? null;
            if ($number === null) {
                continue;
            }

            $normalized = $this->normalizeIdentifier($number);
            if ($normalized === '') {
                continue;
            }

            if (!array_key_exists($normalized, $storeNumbers)) {
                $storeNumbers[$normalized] = $normalized;
            }
        }

        return array_values($storeNumbers);
    }

    /**
     * @param array<int, string> $selectedStoreNumbers
     * @param array<int, string> $availableStoreNumbers
     *
     * @return array{stores: array<int, string>, missing: array<int, string>}
     */
    private function reconcileSelectedStoreNumbers(array $selectedStoreNumbers, array $availableStoreNumbers): array
    {
        $availableLookup = [];

        foreach ($availableStoreNumbers as $storeNumber) {
            $availableLookup[$storeNumber] = true;
        }

        $stores = [];
        $missing = [];

        foreach ($selectedStoreNumbers as $storeNumber) {
            if ($storeNumber === '') {
                continue;
            }

            if (isset($availableLookup[$storeNumber])) {
                if (!in_array($storeNumber, $stores, true)) {
                    $stores[] = $storeNumber;
                }

                continue;
            }

            if (!in_array($storeNumber, $missing, true)) {
                $missing[] = $storeNumber;
            }
        }

        return [
            'stores' => $stores,
            'missing' => $missing,
        ];
    }

    private function buildBrochureNumberForStore(string $brochureNumber, string $storeNumber): string
    {
        $normalizedBrochure = $this->normalizeIdentifier($brochureNumber);
        $normalizedStore = $this->normalizeIdentifier(preg_replace('/\s+/', '', $storeNumber) ?? $storeNumber);

        if ($normalizedBrochure === '') {
            return $normalizedStore;
        }

        if ($normalizedStore === '') {
            return $normalizedBrochure;
        }

        return sprintf('%s_%s', $normalizedBrochure, $normalizedStore);
    }

    private function buildProductNumberForStore(string $productNumber, string $storeNumber): string
    {
        $normalizedProduct = $this->normalizeIdentifier($productNumber);
        $normalizedStore = $this->normalizeIdentifier(preg_replace('/\s+/', '', $storeNumber) ?? $storeNumber);

        if ($normalizedProduct === '') {
            return $normalizedStore;
        }

        if ($normalizedStore === '') {
            return $normalizedProduct;
        }

        return sprintf('%s_%s', $normalizedProduct, $normalizedStore);
    }

    /**
     * @param array<string, mixed> $brochure
     *
     * @return array<string, mixed>
     */
    private function normalizeBrochurePayload(array $brochure, string $expectedIntegrationId, string $brochureId): array
    {
        $integrationId = $this->extractIdFromIri($brochure['integration'] ?? null) ?? $expectedIntegrationId;

        if ($integrationId === '') {
            throw new \RuntimeException(sprintf('Unable to determine integration for brochure %s.', $brochureId));
        }

        if ($expectedIntegrationId !== '' && $integrationId !== $expectedIntegrationId) {
            throw new \RuntimeException(sprintf('Brochure %s does not belong to the selected company.', $brochureId));
        }

        $brochureNumber = $this->normalizeScalar(
            $brochure['brochureNumber'] ?? $brochure['number'] ?? $brochure['id'] ?? '',
        );

        if ($brochureNumber === '') {
            throw new \RuntimeException(sprintf('Brochure %s is missing a brochure number.', $brochureId));
        }

        return [
            'integration' => $integrationId,
            'salesRegion' => $this->normalizeSalesRegion($brochure['salesRegion'] ?? null),
            'pdfUrl' => $this->normalizePdfUrl($brochure),
            'brochureNumber' => $brochureNumber,
            'title' => $this->normalizeScalar($brochure['title'] ?? ''),
            'variety' => $this->normalizeScalar($brochure['variety'] ?? ''),
            'validFrom' => $this->normalizeScalar($brochure['validFrom'] ?? ''),
            'validTo' => $this->normalizeScalar($brochure['validTo'] ?? ''),
            'visibleFrom' => $this->normalizeScalar($brochure['visibleFrom'] ?? ''),
            'trackingPixels' => $this->normalizeTrackingPixels(
                $brochure['trackingPixels'] ?? $brochure['trackingPixel'] ?? null,
            ),
            'layout' => $this->normalizeScalar($brochure['layout'] ?? ''),
            'type' => $this->normalizeScalar($brochure['type'] ?? 'default'),
            'tags' => $this->normalizeTags($brochure['tags'] ?? null),
            'national' => $this->normalizeNational($brochure['national'] ?? $brochure['isNational'] ?? null),
            'gender' => $this->normalizeScalar($brochure['gender'] ?? ''),
            'ageRange' => $this->normalizeScalar($brochure['ageRange'] ?? ''),
            'langCode' => $this->normalizeScalar($brochure['languageCode'] ?? $brochure['langCode'] ?? ''),
            'pdfProcessingOptions' => $this->normalizePdfProcessingOptions($brochure['pdfProcessingOptions'] ?? null),
            'zipcode' => $this->normalizeScalar($brochure['zipcode'] ?? ''),
            'storeNumber' => '',
        ];
    }

    private function preparePdfUrl(string $pdfUrl, string $brochureId): string
    {
        $normalized = trim($pdfUrl);

        if ($normalized === '') {
            return '';
        }

        $pageId = $this->extractBrochurePageId($normalized);

        if ($pageId === null) {
            return $normalized;
        }

        if (isset($this->brochurePdfCache[$brochureId])) {
            return $this->brochurePdfCache[$brochureId];
        }

        $this->ensureBrochurePdfDirectory();

        $fileName = sprintf('brochure_%s.pdf', $brochureId);
        $destination = $this->brochurePdfDir . '/' . $fileName;

        try {
            $localPath = $this->iprotoService->downloadBrochurePdf($pageId, $destination, $brochureId);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf('Failed to download brochure PDF %s for brochure %s.', $pageId, $brochureId),
                0,
                $exception
            );
        }

        try {
            $uploadedUrl = $this->s3Service->upload($localPath);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf('Failed to upload brochure PDF %s for brochure %s.', $pageId, $brochureId),
                0,
                $exception
            );
        }

        $this->brochurePdfCache[$brochureId] = $uploadedUrl;

        return $uploadedUrl;
    }

    private function isDiscoverBrochure(array $payload): bool
    {
        $type = strtolower($this->normalizeScalar($payload['type'] ?? ''));

        return $type === 'discover';
    }

    private function decodeDiscoverLayout(array $brochure): ?array
    {
        $layout = $brochure['layout'] ?? null;

        if (is_string($layout) && $layout !== '') {
            $decoded = json_decode($layout, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($layout)) {
            return $layout;
        }

        return null;
    }

    /**
     * @param array<mixed> $layout
     * @return array<int, string>
     */
    private function collectDiscoverProductIds(array $layout): array
    {
        $ids = [];

        $this->walkDiscoverLayout($layout, static function (string $id) use (&$ids): void {
            $ids[$id] = $id;
        });

        return array_values($ids);
    }

    /**
     * @param array<mixed> $node
     */
    private function walkDiscoverLayout(array $node, callable $collector): void
    {
        if (isset($node['products']) && is_array($node['products'])) {
            foreach ($node['products'] as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $id = $product['id'] ?? null;
                if ($id === null) {
                    continue;
                }

                $collector((string) $id);
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->walkDiscoverLayout($value, $collector);
            }
        }
    }

    /**
     * @param array<mixed> $layoutData
     * @param array<string, int|string> $productIdMap
     */
    private function buildDiscoverLayoutForStore(array $layoutData, array $productIdMap): ?string
    {
        if ($productIdMap === []) {
            return null;
        }

        $updated = $this->replaceDiscoverProductIds($layoutData, $productIdMap);
        $encoded = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode discover brochure layout.');
        }

        return $encoded;
    }

    /**
     * @param array<mixed> $node
     * @param array<string, int|string> $productIdMap
     * @return array<mixed>
     */
    private function replaceDiscoverProductIds(array $node, array $productIdMap): array
    {
        $result = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if ($key === 'products') {
                    $products = [];
                    foreach ($value as $product) {
                        if (!is_array($product)) {
                            $products[] = $product;
                            continue;
                        }

                        $productCopy = $this->replaceDiscoverProductIds($product, $productIdMap);

                        if (isset($productCopy['id'])) {
                            $idKey = (string) $productCopy['id'];
                            if (isset($productIdMap[$idKey])) {
                                $replacement = $productIdMap[$idKey];
                                $productCopy['id'] = is_numeric($replacement) ? (int) $replacement : $replacement;
                            }
                        }

                        $products[] = $productCopy;
                    }

                    $result[$key] = $products;
                    continue;
                }

                $result[$key] = $this->replaceDiscoverProductIds($value, $productIdMap);
                continue;
            }

            if ($key === 'id') {
                $idKey = (string) $value;
                if (isset($productIdMap[$idKey])) {
                    $replacement = $productIdMap[$idKey];
                    $result[$key] = is_numeric($replacement) ? (int) $replacement : $replacement;
                    continue;
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $articleLookup
     * @param array<string, int|string> $productIdLookup
     * @return array<string, int|string>
     */
    private function buildProductIdMapForStore(array $articleLookup, array $productIdLookup, string $brochureId, string $storeNumber): array
    {
        if (!isset($articleLookup[$brochureId][$storeNumber])) {
            return [];
        }

        $map = [];

        foreach ($articleLookup[$brochureId][$storeNumber] as $originalProductId => $articleNumber) {
            if (!isset($productIdLookup[$articleNumber])) {
                throw new \RuntimeException(sprintf(
                    'Duplicated product "%s" for brochure %s and store %s is not available.',
                    $articleNumber,
                    $brochureId,
                    $storeNumber
                ));
            }

            $map[(string) $originalProductId] = $productIdLookup[$articleNumber];
        }

        return $map;
    }

    /**
     * @param array<string|int, array{number: string, data: array<string, mixed>}> $productDetails
     * @param array<string, array<int, string>> $productsByBrochure
     * @param array<int, string> $storeNumbers
     * @return array{
     *     products: array<int, Product>,
     *     articleNumbers: array<int, string>,
     *     lookup: array<string, array<string, array<string, string>>>
     * }
     */
    private function prepareDiscoverProductsForStores(
        array $productDetails,
        array $productsByBrochure,
        array $storeNumbers,
        string $companyId,
    ): array {
        $products = [];
        $lookup = [];

        foreach ($productsByBrochure as $brochureId => $productIds) {
            foreach ($storeNumbers as $storeNumber) {
                foreach ($productIds as $productId) {
                    if (!isset($productDetails[$productId])) {
                        continue;
                    }

                    $detail = $productDetails[$productId];
                    $newNumber = $this->buildProductNumberForStore($detail['number'], $storeNumber);

                    $lookup[$brochureId][$storeNumber][(string) $productId] = $newNumber;

                    if (!isset($products[$newNumber])) {
                        $products[$newNumber] = $this->createProductDtoForStore(
                            $detail['data'],
                            $newNumber,
                            $companyId,
                            $storeNumber,
                        );
                    }
                }
            }
        }

        return [
            'products' => array_values($products),
            'articleNumbers' => array_keys($products),
            'lookup' => $lookup,
        ];
    }

    private function createProductDtoForStore(
        array $productData,
        string $newProductNumber,
        string $companyId,
        string $storeNumber,
    ): Product {
        $integrationId = $this->extractIdFromIri($productData['integration'] ?? null) ?? $companyId;

        $payload = [
            'integration' => $integrationId,
            'productNumber' => $newProductNumber,
            'title' => $this->normalizeScalar($productData['title'] ?? ''),
            'description' => $this->normalizeScalar($productData['description'] ?? ''),
            'price' => $this->normalizeScalar($productData['price'] ?? ''),
            'currency' => $this->normalizeScalar($productData['currency'] ?? ''),
            'secondaryPrice' => $this->normalizeScalar($productData['secondaryPrice'] ?? ''),
            'secondaryCurrency' => $this->normalizeScalar($productData['secondaryCurrency'] ?? ''),
            'priceIsVariable' => $this->normalizeBooleanFlag($productData['priceIsVariable'] ?? null),
            'manufacturerPrice' => $this->normalizeScalar($productData['manufacturerPrice'] ?? ''),
            'secondaryManufacturerPrice' => $this->normalizeScalar($productData['secondaryManufacturerPrice'] ?? ''),
            'manufacturerNumber' => $this->normalizeScalar($productData['manufacturerNumber'] ?? ''),
            'gtin' => $this->normalizeScalar($productData['gtin'] ?? ''),
            'languageCode' => $this->normalizeScalar($productData['languageCode'] ?? ''),
            'keywords' => $this->normalizeProductKeywords($productData['keywords'] ?? null),
            'trackingPixels' => $this->normalizeProductTrackingPixels($productData['trackingPixels'] ?? null),
            'url' => $this->normalizeScalar($productData['url'] ?? ''),
            'brandText' => $this->normalizeScalar($productData['brandText'] ?? ''),
            'brandImage' => $this->normalizeScalar($productData['brandImage'] ?? ''),
            'amount' => $this->normalizeScalar($productData['amount'] ?? ''),
            'size' => $this->normalizeScalar($productData['size'] ?? ''),
            'color' => $this->normalizeScalar($productData['color'] ?? ''),
            'unitType' => $this->normalizeScalar($productData['unitType'] ?? ''),
            'subTitle' => $this->normalizeScalar($productData['subTitle'] ?? ''),
            'salesRegion' => $this->normalizeSalesRegion($productData['salesRegion'] ?? null),
            'validFrom' => $this->normalizeScalar($productData['validFrom'] ?? ''),
            'validTo' => $this->normalizeScalar($productData['validTo'] ?? ''),
            'visibleFrom' => $this->normalizeScalar($productData['visibleFrom'] ?? ''),
            'hidden' => $this->normalizeBooleanFlag($productData['hidden'] ?? null),
            'additionalProperties' => $this->normalizeScalar($productData['additionalProperties'] ?? ''),
            'shipping' => $this->normalizeScalar($productData['shipping'] ?? ''),
            'variants' => $this->normalizeProductVariants($productData['variants'] ?? null),
        ];

        return Product::fromArray($payload);
    }

    /**
     * @param array<int, string> $articleNumbers
     * @return array<string, array<string, mixed>>
     */
    private function waitForDuplicatedProducts(string $companyId, array $articleNumbers): array
    {
        $pending = array_values(array_unique(array_map('strval', $articleNumbers)));

        if ($pending === []) {
            return [];
        }

        $results = [];
        $attempts = 0;
        $maxAttempts = 60;

        while ($pending !== [] && $attempts < $maxAttempts) {
            foreach ($pending as $index => $articleNumber) {
                try {
                    $product = $this->iprotoService->findProductsByNumber($companyId, $articleNumber);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException(sprintf('Unable to load duplicated product "%s".', $articleNumber), 0, $exception);
                }

                if ($product !== false) {
                    $results[$articleNumber] = $product;
                    unset($pending[$index]);
                }
            }

            if ($pending === []) {
                break;
            }

            ++$attempts;
            $pending = array_values($pending);
            usleep(500000);
        }

        if ($pending !== []) {
            throw new \RuntimeException('Timed out waiting for duplicated products to become available.');
        }

        return $results;
    }

    /**
     * @param array<string, array<string, mixed>> $productRecords
     * @return array<string, int|string>
     */
    private function mapProductIdsByArticle(array $productRecords): array
    {
        $map = [];

        foreach ($productRecords as $articleNumber => $record) {
            $id = $record['id'] ?? null;

            if ($id === null) {
                continue;
            }

            $map[$articleNumber] = is_numeric($id) ? (int) $id : (string) $id;
        }

        return $map;
    }

    private function normalizeBooleanFlag(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return $value !== 0 ? '1' : '0';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return '0';
            }

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return '0';
    }

    private function normalizeProductKeywords(mixed $value): string
    {
        return $this->normalizeTags($value);
    }

    private function normalizeProductTrackingPixels(mixed $value): string
    {
        return $this->normalizeTrackingPixels($value);
    }

    private function normalizeProductVariants(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value) && $value !== []) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    private function normalizePdfUrl(array $brochure): string
    {
        $candidates = [
            $brochure['pdfUrl'] ?? null,
            $brochure['pdf_url'] ?? null,
            $brochure['pdf'] ?? null,
        ];

        if (isset($brochure['pages']) && is_array($brochure['pages']) && $brochure['pages'] !== []) {
            foreach ($brochure['pages'] as $page) {
                if (is_array($page)) {
                    $candidates[] = $page['pdfUrl'] ?? $page['pdf_url'] ?? $page['url'] ?? $page['file'] ?? null;
                } else {
                    $candidates[] = $page;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $value = $this->normalizeScalar($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function ensureBrochurePdfDirectory(): void
    {
        if ($this->brochurePdfDirectoryEnsured) {
            return;
        }

        $directory = $this->brochurePdfDir;

        if ($directory !== '' && !str_starts_with($directory, '/') && str_contains($directory, '://') === false) {
            $workingDirectory = getcwd() ?: '';
            if ($workingDirectory !== '') {
                $directory = rtrim($workingDirectory, '/') . '/' . ltrim($directory, '/');
            }
        }

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Unable to create brochure PDF directory "%s".', $directory));
            }
        }

        $this->brochurePdfDir = rtrim($directory, '/');
        $this->brochurePdfDirectoryEnsured = true;
    }

    private function extractBrochurePageId(string $pdfUrl): ?string
    {
        if ($pdfUrl === '') {
            return null;
        }

        if (preg_match('~brochure_pages/(\d+)~', $pdfUrl, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function normalizeSalesRegion(mixed $value): string
    {
        if (is_array($value)) {
            if (isset($value['title'])) {
                return $this->normalizeScalar($value['title']);
            }

            if (isset($value['name'])) {
                return $this->normalizeScalar($value['name']);
            }

            if (isset($value['id'])) {
                $value = $value['id'];
            } elseif (isset($value['@id'])) {
                $value = $value['@id'];
            }
        }

        $string = $this->normalizeScalar($value);
        if ($string !== '') {
            return $string;
        }

        $extracted = $this->extractIdFromIri($value);

        return $extracted ?? '';
    }

    private function normalizeTrackingPixels(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $entry) {
                if (is_array($entry)) {
                    $entry = $entry['url'] ?? $entry['code'] ?? $entry['pixel'] ?? $entry['value'] ?? null;
                }

                $normalized = $this->normalizeScalar($entry);
                if ($normalized !== '') {
                    $items[] = $normalized;
                }
            }

            return implode("\n", $items);
        }

        return '';
    }

    private function normalizeTags(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $entry) {
                if (is_array($entry)) {
                    $entry = $entry['name'] ?? $entry['title'] ?? $entry['label'] ?? $entry['value'] ?? null;
                }
                $normalized = $this->normalizeScalar($entry);
                if ($normalized !== '') {
                    $items[] = $normalized;
                }
            }

            return implode(', ', $items);
        }

        return '';
    }

    private function normalizeNational(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true' || $normalized === 'yes') {
                return 1;
            }

            if ($normalized === 'false' || $normalized === 'no') {
                return 0;
            }

            if (is_numeric($normalized)) {
                return (int) $normalized;
            }
        }

        return 0;
    }

    private function normalizePdfProcessingOptions(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function normalizeScalar(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = $this->sanitizeUtf8($value);
        }

        return $value;
    }

    private function normalizeIdentifier(mixed $value): string
    {
        $normalized = $this->normalizeScalar($value);

        return $normalized;
    }

    /**
     * @param array<int, int|string> $values
     *
     * @return array<int, string>
     */
    private function normalizeIdentifiers(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $stringValue = $this->normalizeIdentifier($value);
            if ($stringValue === '' || in_array($stringValue, $normalized, true)) {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return $normalized;
    }

    private function extractImportId(array $import): ?string
    {
        $identifier = $import['@id'] ?? null;
        if (!is_string($identifier) || $identifier === '') {
            return null;
        }

        $identifier = trim($identifier, '/');
        if ($identifier === '') {
            return null;
        }

        $segments = explode('/', $identifier);
        $lastSegment = end($segments);

        return $lastSegment !== false ? $lastSegment : null;
    }

    private function extractIdFromIri(mixed $value): ?string
    {
        if (is_array($value)) {
            if (isset($value['id'])) {
                return $this->extractIdFromIri($value['id']);
            }

            if (isset($value['@id'])) {
                return $this->extractIdFromIri($value['@id']);
            }

            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $path = parse_url($trimmed, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $trimmed = $path;
        }

        $trimmed = trim($trimmed, '/');
        if ($trimmed === '') {
            return null;
        }

        $segments = explode('/', $trimmed);
        $lastSegment = end($segments);

        return $lastSegment !== false ? $lastSegment : null;
    }

    private function sanitizeUtf8(string $value): string
    {
        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($sanitized) && $sanitized !== '') {
            return trim($sanitized);
        }

        $converted = @utf8_encode($value);
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return trim($converted);
        }

        return '';
    }
}
