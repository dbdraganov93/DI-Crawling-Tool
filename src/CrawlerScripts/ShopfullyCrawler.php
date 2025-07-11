<?php

namespace App\CrawlerScripts;

use App\Dto\Brochure;
use App\Entity\ShopfullyLog;
use App\Service\IprotoService;
use App\Service\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ShopfullyService;
use App\Service\CsvService;
use App\Service\BrochureService;

ini_set('memory_limit', '512M'); // or '1G' if needed

class ShopfullyCrawler
{
    private EntityManagerInterface $em;
    private ShopfullyService $shopfullyService;
    private IprotoService $iprotoService;
    private string $company;

    public function __construct(
        EntityManagerInterface $em,
        ShopfullyService $shopfullyService,
        IprotoService $iprotoService
    ) {
        $this->em = $em;
        $this->shopfullyService = $shopfullyService;
        $this->iprotoService = $iprotoService;
    }

    public function crawl(array $requestData): void
    {
        $this->company = $requestData['company'];
        $locale = $requestData['locale'];
        $brochureDetails = $requestData['numbers'];
        dd($requestData);
        $brochureService = new BrochureService($this->company);
        $storeService = new StoreService($this->company);

        $brochures = [];
        foreach ($brochureDetails as $brochureDetail) {
            $sfBrochure = $this->shopfullyService->getBrochure($brochureDetail['number'], $locale);
            $brochureData = $sfBrochure['brochureData'];
            $brochureData['trackingPixel'] = $brochureDetail['tracking_pixel'] ?? '';
            $validFrom = $this->normalizeDate($brochureDetail['validity_start'] ?? null) ?? new \DateTime();

            $validTo = $this->normalizeDate($brochureDetail['validity_end'] ?? null) ?? clone $validFrom;
            $validTo = (clone $validTo)->setTime(23, 59, 59);

            $visibleFrom = $this->normalizeDate($brochureDetail['visibility_start'] ?? null) ?? clone $validFrom;

            $brochureData['start_date']   = $validFrom->format('Y-m-d H:i:s');
            $brochureData['end_date']     = $validTo->format('Y-m-d H:i:s');
            $brochureData['visible_from'] = $visibleFrom->format('Y-m-d H:i:s');

            $this->createStores($sfBrochure['brochureStores'], $storeService);
            $brochures[] = $this->createBrochure($brochureData);
        }

        $csvService = new CsvService();
        $brochureCsv = $csvService->createCsvFromBrochure($brochureService);
        $storeCsv = $csvService->createCsvFromStores($storeService);
        // dd($brochureCsv, $storeCsv);
        $storeImport = $this->iprotoService->importData($storeCsv);
        $brochureImport = $this->iprotoService->importData($brochureCsv);

        $this->log($locale, $requestData, $storeImport, 'stores');
        $this->log($locale, $requestData, $brochureImport, 'brochures');
    }

    private function log($locale, array $data, $import, $type): void
    {
        $import['id'] = explode('/', $import['@id']);
        $import['id'] = end($import['id']);
        $log = new ShopfullyLog();
        $log->setCompanyName($this->company);
        $log->setIprotoId($this->company);
        $log->setLocale($locale);
        $log->setData($data);
        $log->setImportType($type);
        $log->setStatus($import['status']);
        $log->setNoticesCount($import['noticesCount'] ?? 0);
        $log->setWarningsCount($import['warningsCount'] ?? 0);
        $log->setErrorsCount($import['errorsCount'] ?? 0);
        $log->setImportId($import['id']);
        $log->setCreatedAt(new \DateTime());

        $this->em->persist($log);
        $this->em->flush();
    }

    private function createStores(array $stores, StoreService $storeService): void
    {
        foreach ($stores as $store) {
            $storeService
                ->setStoreNumber($store['Store']['id'])
                ->setCity($store['Store']['city'])
                ->setZipcode($store['Store']['zip'])
                ->setStreet($store['Store']['address'])
                ->setLatitude($store['Store']['lat'])
                ->setLongitude($store['Store']['lng'])
                ->setTitle($store['Store']['more_info'])
                ->setText($store['Store']['description'])
                ->setPhone($store['Store']['phone'])
                ->setFax($store['Store']['fax'])
                ->addCurrentStore();
        }
    }

    private function createBrochure(array $brochureData): Brochure
    {
        $brochureDataToPass = [
            'integration' => $this->company,
            'pdfUrl' => $brochureData['pdf_url'],
            'brochureNumber' => $brochureData['id'],
            'title' => $brochureData['title'],
            'validFrom' => $brochureData['start_date'],
            'validTo' => $brochureData['end_date'],
            'visibleFrom' => $brochureData['visible_from'],
            'trackingPixels' => $brochureData['trackingPixel'],
            'storeNumber' => $brochureData['stores'],
        ];

        return Brochure::fromArray($brochureDataToPass);
    }

    private function normalizeDate(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTime($value->format('c'));
        }
        if (is_array($value) && isset($value['date'])) {
            return new \DateTime($value['date']);
        }
        if (is_string($value) && $value !== '') {
            return new \DateTime($value);
        }
        return null;
    }
}
