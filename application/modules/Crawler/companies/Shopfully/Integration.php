<?php

class Crawler_Company_Shopfully_Integration extends Crawler_Generic_Company
{
    private const SPREADSHEET_ID = '1skaLWAlMqD3d1ZnfaBspUvS3PEUJ104BayZx56JqnAE';
    private const INDUSTRY_MAPPINGS_SPREADSHEET_ID = '1IBurG8qeUag3Xpx53NAPjkHYuTeCPxkNjB3rPG5WXRA';
    private const DEFAULT_INDUSTRY = 31; // Other
    private const DEFAULT_OWNER = '231'; // Offerista IT

    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleSheetsService;
    private array $industryMappings;

    public function __construct()
    {
        parent::__construct();

        $this->googleSheetsService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->industryMappings = $this->getIndustryMappings();
    }

    public function crawl($companyId)
    {
        $iProtoApi = new Marktjagd_Service_Input_MarktjagdApi();

        $retailersToIntegrate = $this->googleSheetsService->getFormattedInfos(self::SPREADSHEET_ID);

        foreach ($retailersToIntegrate as $retailerDetails) {
            $retailerData = $this->getRetailer($retailerDetails);
            if (empty($retailerData)) {
                $this->_logger->warn('Retailer not found: ' . json_encode($retailerDetails));
                continue;
            }

            $integrationData = $this->getIntegrationData($retailerData, (string) $retailerDetails['BT owner']);

            try {
                $iProtoApi->createIntegration($integrationData);
                $this->_logger->info(sprintf('Integration created: %s', $integrationData['title']));
            } catch (Exception $e) {
                $this->_logger->err(sprintf('Integration could not be created: %s, reason: %s', $integrationData['title'], $e->getMessage()));
            }
        }

        return $this->getSuccessResponse();
    }

    private function getIndustryMappings(): array
    {
        $mappingsData = $this->googleSheetsService->getFormattedInfos(
            self::INDUSTRY_MAPPINGS_SPREADSHEET_ID,
            'A1',
            'C',
            'Industry mappings'
        );

        $industryMappings = [];
        foreach ($mappingsData as $mapping) {
            $industryMappings[$mapping['sf_category_id']] = [
                'primary' => $mapping['og_primary_industry_id'],
                'secondary' => $mapping['og_secondary_industry_id'],
            ];
        }

        return $industryMappings;
    }

    private function getRetailer(array $retailerDetails): ?Shopfully_Entity_Retailer
    {
        $shopfullyRetailerApi = new Shopfully_Service_RetailerApi($retailerDetails['lang']);

        $retailerData = null;
        if (!empty($retailerDetails['retailer id'])) {
            $retailerData = $shopfullyRetailerApi->getRetailerById($retailerDetails['retailer id']);
        } elseif (!empty($retailerDetails['retailer name'])) {
            $retailerData = $shopfullyRetailerApi->getRetailerByName($retailerDetails['retailer name']);
        }

        return $retailerData;
    }

    private function getIntegrationData(Shopfully_Entity_Retailer $retailerData, string $owner = null): array
    {
        $url = preg_replace('/^http:/', 'https:', $retailerData->getUrl());
        if (!preg_match('/^https:/', $url)) {
            $url = 'https://' . $url;
        }

        $categoryId = $retailerData->getCategoryId();

        return [
            'name' => $retailerData->getSlug(),
            'primaryIndustry' => '/api/industries/' . $this->industryMappings[$categoryId]['primary'] ?: self::DEFAULT_INDUSTRY,
            'secondaryIndustries' => $this->industryMappings[$categoryId]['secondary'] ? ['/api/industries/' . $this->industryMappings[$categoryId]['secondary']] : [],
            'companyUrl' => $url,
            'owner' => '/api/owners/' . $owner ?: self::DEFAULT_OWNER,
            'isVisible' => true,
            'description' => substr($retailerData->getDescription(), 0, 500),
            'title' => $retailerData->getName(),
            'popularityBrochureGroupGeneration' => false,
            'isLightCustomer' => false,
            'ignoreOnProspectoSync' => true,
        ];
    }
}
