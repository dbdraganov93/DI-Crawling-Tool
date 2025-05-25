<?php
namespace App\CrawlerScripts;

use App\Entity\Company;
use App\Entity\ShopfullyLog;
use App\Service\IprotoService;
use App\Service\S3Service;
use App\Service\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ShopfullyService;
use Symfony\Component\HttpFoundation\Response;
use App\Service\CsvService;
use App\Service\BrochureService;
class ShopfullyCrawler
{
    private EntityManagerInterface $em;
    private IprotoService $iprotoService;
    private ShopfullyService $shopfullyService;
    private S3Service $s3Service;
    public function __construct(EntityManagerInterface $em, ShopfullyService $shopfullyService, IprotoService $iprotoService, S3Service $s3Service)
    {
        $this->em = $em;
        $this->shopfullyService = $shopfullyService;
        $this->iprotoService = $iprotoService;
        $this->s3Service = $s3Service;
    }

    public function crawl(array $brochure): void
    {

        $company = $brochure['company'];
        $locale = $brochure['locale'];
        $brochures = $brochure['numbers'];
        $timeZone = $brochure['timezone'];

        $brochureService = new BrochureService($company, $timeZone);
        $storeService = new StoreService($company);
        foreach ($brochures as $brochure) {

            $brochureData = $this->shopfullyService->getBrochure($brochure['number'], $locale);
            $stores = $this->shopfullyService->fetchStoresByBrochureId($brochure['number'], $locale);

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

        foreach ($storeService->getStores() as $store) {
          //  $store['integration'] = ['phone'=>'', 'fax' => ''];
            //$store['owner'] = 231;
            $storeResult = $this->iprotoService->importStoresInIproto($store);
            var_dump($store);
            dd($storeResult);
        }


        $result = $this->iprotoService->createBrochures($brochureService->getBrochures());
        $status = null;
        if (!empty($result) && is_array($result)) {
            $firstEntry = reset($result);
            if (is_array($firstEntry) && isset($firstEntry['status'])) {
                $status = $firstEntry['status']; // e.g., "submitted"
            }
        }
        // Logging
        $log = new ShopfullyLog();
        $log->setCompanyName($company);
        $log->setIprotoId($company);
        $log->setLocale($locale);
        $log->setData($brochures);
        $log->setCreatedAt(new \DateTime());
        $log->setStatus($status);

        $this->em->persist($log);
        $this->em->flush();
    }

}
