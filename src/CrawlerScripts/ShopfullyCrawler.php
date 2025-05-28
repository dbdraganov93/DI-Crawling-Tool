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

class ShopfullyCrawler
{
    private EntityManagerInterface $em;
    private ShopfullyService $shopfullyService;
    private IprotoService $iprotoService;
    private S3Service $s3Service;

    private string $company;

    public function __construct(
        EntityManagerInterface $em,
        ShopfullyService $shopfullyService,
        IprotoService $iprotoService,
        S3Service $s3Service
    ) {
        $this->em = $em;
        $this->shopfullyService = $shopfullyService;
        $this->iprotoService = $iprotoService;
        $this->s3Service = $s3Service;
    }

    public function crawl(array $brochure): void
    {
        $this->company = $brochure['company'];
        $locale = $brochure['locale'];
        $brochures = $brochure['numbers'];
        $timeZone = $brochure['timezone'];

        $brochureService = new BrochureService($this->company, $timeZone);
        $storeService = new StoreService($this->company);

        foreach ($brochures as $brochure) {
            $brochureData = $this->shopfullyService->getBrochure($brochure['number'], $locale);
            $stores = $this->shopfullyService->fetchStoresByBrochureId($brochure['number'], $locale);

            $this->createStores($stores, $storeService);
            $this->createBrochure($brochureData, $brochureService);
        }

        $csvService = new CsvService();
        //dd($csvService->createCsvFromStores($storeService));
        dd($this->iprotoService->importData($csvService->createCsvFromStores($storeService)));
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
            ->addCurrentBrochure();
    }
}
