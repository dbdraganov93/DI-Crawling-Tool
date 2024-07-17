<?php

namespace Marktjagd\Service\IprotoApi;

use Crawler_Generic_Response;
use Marktjagd_Collection_Api_Article;
use Marktjagd_Collection_Api_Distribution;
use Marktjagd_Collection_Api_Store;
use Marktjagd_Database_Entity_CrawlerConfig;

/**
 * Interface describing all API functions which should be implemented and usable in APIv3 as well as iProto.
 */
interface ApiServiceInterface
{

    public function createStore(array $store): void;

    public function findCompanyByName(int $ownerId, string $searchString): ?array;

    /**
     * Returns all stores for a given company (including their assigned, named distributions).
     */
    public function findStoresByCompany(int $companyId, bool $visibleOnly = true): Marktjagd_Collection_Api_Store;

    /**
     * Returns all stores with the same storeNumber.
     */
    public function findStoreByStoreNumber(string $storeNumber, string $companyId = '');


    /**
     * Returns all (named) distributions of a company.
     */
    function findDistributionsByCompany(int $companyId): Marktjagd_Collection_Api_Distribution;

    /**
     * Returns all stores which are either assigned to or not assigned to the given, named distribution.
     *
     * @param int $companyId
     * @param ?string $distribution         The name of the distribution or null for stores not assigned to any distribution
     * @param bool $excludeDistribution     True: Negates the selection
     * @param bool $visibleOnly             True: Only visible stores
     * @return Marktjagd_Collection_Api_Store|bool  False on error, otherwise a collection of stores
     */
    public function findStoresByDistribution(int $companyId, ?string $distribution, bool $excludeDistribution = false, bool $visibleOnly = true);

    /**
     * Returns the attributes of all active brochures for a given company.
     *
     * @param int $companyId
     * @return array|bool       False on error, otherwise an associative array with the brochure-attributes, indexed by the brochure-id
     */
    public function findActiveBrochuresByCompany(int $companyId);

    /**
     * Returns the attributes of all stores of a given company which are assigned to a given, active brochure.
     *
     * @param int $brochureId
     * @param int $companyId
     * @return array|bool       False on error, otherwise an associative array with the store-attributes, indexed by the brochure-id
     */
    public function findStoresWithActiveBrochures(int $brochureId, int $companyId);

    /**
     * Returns the attributes of all active products of a given company.
     *
     * @param int $companyId
     * @return array|bool       False on error, otherwise an associative array with the product-attributes, indexed by the product-id
     */
    public function findActiveArticlesByCompany(int $companyId);

    public function findArticleById(int $companyId, string $id);

    /**
     * Returns the attributes of all stores of a given company.
     *
     * @return boolean|array    False on error, otherwise an associative array with the store-attributes, indexed by the store-id
     */
    public function findAllStoresForCompany(int $companyId, string $status = 'visible');

    /**
     * Returns the timestamp of the last import-job of a given company and type.
     *
     * @param int $companyId
     * @param string $type
     * @param string $status
     * @return bool|string      Date-time of the last import-job or false on error / if not found
     */
    public function findLastImport(int $companyId, string $type, string $status = 'done');

    /**
     * Returns the attributes of a given store as associative array.
     *
     * @param int $storeId
     * @param int $companyId
     * @return array|bool       Array with store-attributes or false on error
     */
    public function findStoreByStoreId(int $storeId, int $companyId);

    public function findStoreNumbersByPostcode(string $postcode, int $companyId);

    /**
     * Returns the attributes of all stores which have brochures assigned to them.
     *
     * @param int $companyId
     * @param string $timeConstraint
     * @return array|bool       False on error, otherwise an associative array with the store-attributes, indexed by the store-id
     */
    public function findStoresWithBrochures(int $companyId, string $timeConstraint = 'current');


    /**
     * Returns the attributes of a company.
     *
     * @param int $companyId
     * @param int|bool $industryId
     * @return array|bool       False on error, otherwise an associative array with the company-attributes
     */
    public function findCompanyByCompanyId(int $companyId, $industryId = false);

    /**
     * Returns the attributes of the first manufacturer-tag for a given article.
     *
     * @param int $companyId
     * @param int $articleId
     * @return array|bool       False on error, otherwise an associative array with the tag-attributes
     */
    public function findManufacturerTagByArticleId(int $companyId, int $articleId);

    /**
     * Returns the attributes of a given article.
     *
     * @param int $companyId
     * @param string $articleNumber
     * @return array|bool       False on error, otherwise an associative array with the article-attributes
     */
    public function findArticleByArticleNumber(int $companyId, string $articleNumber);

    /**
     * Returns the attributes of a given article.
     *
     * @param int $companyId
     * @param string $articleNumber
     * @return array|bool       False on error, otherwise an associative array with the article-attributes
     */
    public function findUpcomingArticleByNumber(int $companyId, string $articleNumber);

    /**
     * Returns an article-collection of all active articles for a given company.
     *
     * @param int $companyId
     * @return bool|Marktjagd_Collection_Api_Article Collection or false on error
     */
    public function getActiveArticleCollection(int $companyId);

    /**
     * Submits a crawled file to be imported.
     */
    public function import(Marktjagd_Database_Entity_CrawlerConfig $eCrawlerConfig, Crawler_Generic_Response $response): Crawler_Generic_Response;

    /**
     * Returns all attributes for a given import-id.
     */
    public function findImportById(int $companyId, int $importId): array;

    public function findBrochureByBrochureNumberAndCompany(string $brochureNumber, int $companyId): ?array;

    public function createSalesRegionFromStoreNumbers(int $integrationId, array $storeNumbers): array;

    public function createSalesRegionForTheWholeCountry(int $integrationId, string $countryCode);

    public function createSalesRegion(array $iprotoCreateSalesRegionRequest);

    public function createBrochure(array $brochure): array;

    public function createBrochureImagesBatch(array $imageUrls): int;

    public function getBrochureImagesByBatchId(string $batchId, int $maxBatchWaitTime = 1024): array;

}
