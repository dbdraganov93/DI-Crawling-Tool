<?php

namespace App\CrawlerScripts;

use App\Entity\ShopfullyLog;
use App\Service\IprotoService;
use App\Service\S3Service;
use App\Service\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ShopfullyService;
use App\Service\CsvService;
use App\Service\BrochureService;
use App\Service\PdfLinkAnnotatorService;

ini_set('memory_limit', '512M'); // or '1G' if needed

class ShopfullyCrawler
{
    private EntityManagerInterface $em;
    private ShopfullyService $shopfullyService;
    private IprotoService $iprotoService;
    private S3Service $s3Service;
    private string $company;
    private PdfLinkAnnotatorService $pdfLinkAnnotatorService;

    public function __construct(
        EntityManagerInterface $em,
        ShopfullyService $shopfullyService,
        IprotoService $iprotoService,
        S3Service $s3Service,
        PdfLinkAnnotatorService $pdfLinkAnnotatorService
    ) {
        $this->em = $em;
        $this->shopfullyService = $shopfullyService;
        $this->iprotoService = $iprotoService;
        $this->s3Service = $s3Service;
        $this->pdfLinkAnnotatorService = $pdfLinkAnnotatorService;
    }

    public function crawl(array $brochure): void
    {
        dd($brochure);
        $this->company = $brochure['company'];
        $locale = $brochure['locale'];
        $brochures = $brochure['numbers'];
        $timeZone = $brochure['timezone'];

        $brochureService = new BrochureService($this->company, $timeZone);
        $storeService = new StoreService($this->company);

        foreach ($brochures as $brochure) {
            $brochureData = $this->shopfullyService->getBrochure($brochure['number'], $locale);
            $brochureData['trackingPixel'] = $brochure['tracking_pixel'];

            $this->createStores($brochureData, $storeService);
            $this->createBrochure($brochureData, $brochureService);
        }

        $csvService = new CsvService();
        $brochureCsv = $csvService->createCsvFromBrochure($brochureService);
        $storeCsv = $csvService->createCsvFromStores($storeService);

        $storeImport = $this->iprotoService->importData($storeCsv);
        $brochureImport = $this->iprotoService->importData($brochureCsv);

        $this->log($locale, $brochures, $storeImport, 'stores');
        $this->log($locale, $brochures, $brochureImport, 'brochures');
    }

    private function log($locale, $brochures, $import, $type): void
    {
        $import['id'] = explode('/', $import['@id']);
        $import['id'] = end($import['id']);
        $log = new ShopfullyLog();
        $log->setCompanyName($this->company);
        $log->setIprotoId($this->company);
        $log->setLocale($locale);
        $log->setData($brochures);
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
}
