<?php

declare(strict_types=1);

namespace App\Service\BookingWizard;

use App\Dto\Brochure;
use App\Service\CsvService;
use App\Service\IprotoService;
use InvalidArgumentException;

class BrochureActionService
{
    public const ACTION_DUPLICATE_PER_STORE = 'duplicate_per_store';
    public const ACTION_DUPLICATE_PER_SELECTED_STORE = 'duplicate_per_selected_store';

    public function __construct(
        private IprotoService $iprotoService,
        private CsvService $csvService,
    ) {
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
        $normalizedCompanyId = $this->normalizeIdentifier($companyId);
        $normalizedOwnerId = $this->normalizeIdentifier($ownerId);
        $normalizedBrochureIds = $this->normalizeIdentifiers($brochureIds);

        if ($normalizedCompanyId === '') {
            throw new InvalidArgumentException('A company must be selected to duplicate brochures.');
        }

        if ($normalizedOwnerId === '') {
            throw new InvalidArgumentException('An owner must be selected to duplicate brochures.');
        }

        if ($normalizedBrochureIds === []) {
            throw new InvalidArgumentException('Select at least one brochure to duplicate.');
        }

        $stores = $this->iprotoService->getStoresByCompany($normalizedCompanyId);
        $storeNumbers = $this->extractStoreNumbers($stores);

        if ($storeNumbers === []) {
            throw new \RuntimeException('No stores found for the selected company.');
        }

        return $this->duplicateBrochuresForStores(
            $normalizedCompanyId,
            $normalizedBrochureIds,
            $storeNumbers,
        );
    }

    /**
     * @param array<int, int|string> $brochureIds
     * @param array<int, int|string> $storeNumbers
     *
     * @return array{
     *     summary: array{
     *         selectedBrochures: int,
     *         stores: int,
     *         storeNumbers: array<int, string>,
     *         generated: int,
     *     },
     *     csv: array<string, mixed>,
     *     import: array<string, mixed>,
     *     importId: string|null,
     * }
     */
    public function duplicateBrochuresPerSelectedStore(
        string $companyId,
        string $ownerId,
        array $brochureIds,
        array $storeNumbers
    ): array {
        $normalizedCompanyId = $this->normalizeIdentifier($companyId);
        $normalizedOwnerId = $this->normalizeIdentifier($ownerId);
        $normalizedBrochureIds = $this->normalizeIdentifiers($brochureIds);
        $normalizedStoreNumbers = $this->normalizeIdentifiers($storeNumbers);

        if ($normalizedCompanyId === '') {
            throw new InvalidArgumentException('A company must be selected to duplicate brochures.');
        }

        if ($normalizedOwnerId === '') {
            throw new InvalidArgumentException('An owner must be selected to duplicate brochures.');
        }

        if ($normalizedBrochureIds === []) {
            throw new InvalidArgumentException('Select at least one brochure to duplicate.');
        }

        if ($normalizedStoreNumbers === []) {
            throw new InvalidArgumentException('Specify at least one store number to duplicate brochures.');
        }

        $stores = $this->iprotoService->getStoresByCompany($normalizedCompanyId);
        $availableStoreNumbers = $this->extractStoreNumbers($stores);

        if ($availableStoreNumbers === []) {
            throw new \RuntimeException('No stores found for the selected company.');
        }

        $missingStoreNumbers = array_values(array_diff($normalizedStoreNumbers, $availableStoreNumbers));

        if ($missingStoreNumbers !== []) {
            throw new InvalidArgumentException(sprintf(
                'Some store numbers are not available for the selected company: %s.',
                implode(', ', $missingStoreNumbers),
            ));
        }

        return $this->duplicateBrochuresForStores(
            $normalizedCompanyId,
            $normalizedBrochureIds,
            $normalizedStoreNumbers,
        );
    }

    /**
     * @param array<int, string> $brochureIds
     * @param array<int, string> $storeNumbers
     *
     * @return array{
     *     summary: array{
     *         selectedBrochures: int,
     *         stores: int,
     *         storeNumbers: array<int, string>,
     *         generated: int,
     *     },
     *     csv: array<string, mixed>,
     *     import: array<string, mixed>,
     *     importId: string|null,
     * }
     */
    private function duplicateBrochuresForStores(
        string $companyId,
        array $brochureIds,
        array $storeNumbers
    ): array {
        $duplicatedBrochures = $this->buildBrochureCopies($companyId, $brochureIds, $storeNumbers);

        if ($duplicatedBrochures === []) {
            throw new \RuntimeException('No brochures were duplicated.');
        }

        $brochureCsv = $this->csvService->createCsvFromBrochure($duplicatedBrochures, $companyId);

        $import = $this->iprotoService->importData($brochureCsv);

        return [
            'summary' => [
                'selectedBrochures' => count($brochureIds),
                'stores' => count($storeNumbers),
                'storeNumbers' => array_values($storeNumbers),

                'generated' => count($duplicatedBrochures),
            ],
            'csv' => $brochureCsv,
            'import' => $import,
            'importId' => $this->extractImportId($import),
        ];
    }

    /**
     * @param array<int, string> $brochureIds
     * @param array<int, string> $storeNumbers
     *
     * @return array<int, Brochure>
     */
    private function buildBrochureCopies(string $companyId, array $brochureIds, array $storeNumbers): array
    {
        $duplicatedBrochures = [];

        foreach ($brochureIds as $brochureId) {
            $brochureDetail = $this->iprotoService->getBrochureDetails($brochureId);
            $basePayload = $this->normalizeBrochurePayload($brochureDetail, $companyId, $brochureId);

            foreach ($storeNumbers as $storeNumber) {
                $payload = $basePayload;
                $payload['brochureNumber'] = $this->buildBrochureNumberForStore($basePayload['brochureNumber'], $storeNumber);
                $payload['storeNumber'] = $storeNumber;

                $duplicatedBrochures[] = Brochure::fromArray($payload);
            }
        }

        return $duplicatedBrochures;
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

    private function normalizePdfUrl(array $brochure): string
    {
        $candidates = [
            $brochure['pdfUrl'] ?? null,
            $brochure['pdf_url'] ?? null,
            $brochure['pdf'] ?? null,
        ];

        if (isset($brochure['pages']) && is_array($brochure['pages']) && $brochure['pages'] !== []) {
            $candidates[] = $brochure['pages'][0];
        }

        foreach ($candidates as $candidate) {
            $value = $this->normalizeScalar($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
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

        return trim($value);
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
}
