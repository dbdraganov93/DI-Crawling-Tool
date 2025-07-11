<?php

namespace App\CrawlerScripts;

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

    public function crawl(array $brochuresData): void
    {
        $this->company = $brochuresData['company'];
        $locale = $brochuresData['locale'];
        $brochures = $brochuresData['numbers'];

        $brochureService = new BrochureService($this->company);
        $storeService = new StoreService($this->company);

        foreach ($brochures as $brochure) {
            $brochureData = $this->shopfullyService->getBrochure($brochure['number'], $locale);
            $brochureData['trackingPixel'] = $brochure['tracking_pixel'];
            $validFrom = $this->normalizeDate($brochure['validity_start'] ?? null) ?? new \DateTime();

            $validTo = $this->normalizeDate($brochure['validity_end'] ?? null) ?? clone $validFrom;
            $validTo = (clone $validTo)->setTime(23, 59, 59);

            $visibleFrom = $this->normalizeDate($brochure['visibility_start'] ?? null) ?? clone $validFrom;

            $brochureData['brochureData']['data'][0]['Flyer']['start_date']   = $validFrom->format('Y-m-d H:i:s');
            $brochureData['brochureData']['data'][0]['Flyer']['end_date']     = $validTo->format('Y-m-d H:i:s');
            $brochureData['brochureData']['data'][0]['Flyer']['visible_from'] = $visibleFrom->format('Y-m-d H:i:s');

            $this->createStores($brochureData, $storeService);
            $this->createBrochure($brochureData, $brochureService);
        }

        $csvService = new CsvService();
        $brochureCsv = $csvService->createCsvFromBrochure($brochureService);
        $storeCsv = $csvService->createCsvFromStores($storeService);
        // dd($brochureCsv, $storeCsv);
        $storeImport = $this->iprotoService->importData($storeCsv);
        $brochureImport = $this->iprotoService->importData($brochureCsv);

        $this->log($locale, $brochuresData, $storeImport, 'stores');
        $this->log($locale, $brochuresData, $brochureImport, 'brochures');
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
        foreach ($stores['brochureStores'] as $store) {
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

    private function createBrochure(array $brochureData, BrochureService $brochureService): void
    {
        $brochureService
            ->setPdfUrl($brochureData['publicationData']['data'][0]['Publication']['pdf_url'])
            ->setBrochureNumber($brochureData['brochureData']['data'][0]['Flyer']['id'])
            ->setTitle($brochureData['brochureData']['data'][0]['Flyer']['title'])
            ->setVariety('leaflet')
            ->setValidFrom($brochureData['brochureData']['data'][0]['Flyer']['start_date'])
            ->setValidTo($brochureData['brochureData']['data'][0]['Flyer']['end_date'])
            ->setVisibleFrom($brochureData['brochureData']['data'][0]['Flyer']['start_date'])
            ->setTrackingPixels($brochureData['trackingPixel'] ?? '')
            ->setStoreNumber($brochureData['brochureData']['data'][0]['Flyer']['stores'])
            ->addCurrentBrochure();
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
