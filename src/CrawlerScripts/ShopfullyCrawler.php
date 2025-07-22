<?php

namespace App\CrawlerScripts;

use App\Dto\Brochure;
use App\Dto\Store;
use App\Entity\ShopfullyLog;
use App\Service\IprotoService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ShopfullyService;
use App\Service\CsvService;

ini_set('memory_limit', '512M'); // or '1G' if needed

class ShopfullyCrawler
{
    private EntityManagerInterface $em;
    private ShopfullyService $shopfullyService;
    private IprotoService $iprotoService;
    private string $company;
    private ?string $author = null;

    public function __construct(
        EntityManagerInterface $em,
        ShopfullyService $shopfullyService,
        IprotoService $iprotoService
    ) {
        $this->em = $em;
        $this->shopfullyService = $shopfullyService;
        $this->iprotoService = $iprotoService;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function crawl(array $requestData): void
    {
        $this->company = $requestData['company'];
        $locale = $requestData['locale'];
        $brochureDetails = $requestData['numbers'];

        $stores = [];
        $brochures = [];
        foreach ($brochureDetails as $brochureDetail) {
            $sfBrochure = $this->shopfullyService->getBrochure($brochureDetail['number'], $locale);

            $stores = array_merge($stores, $this->createStores($sfBrochure['brochureStores']));

            $brochureData = $this->prepareBrochureData($sfBrochure['brochureData'], $brochureDetail);
            dd($brochureData);
            $brochures[] = $this->createBrochure($brochureData);

            if (!empty($requestData['prefix']) || !empty($requestData['suffix'])) {
                $brochureData['number'] = $requestData['prefix'] . $brochureData['number'] . $requestData['suffix'];
                $brochures[] = $this->createBrochure($brochureData);
            }
        }

        $csvService = new CsvService();
        $brochureCsv = $csvService->createCsvFromBrochure($brochures, $this->company);
        $storeCsv = $csvService->createCsvFromStores($stores, $this->company);
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
        $log->setAuthor($this->author);

        $this->em->persist($log);
        $this->em->flush();
    }

    private function createStores(array $sfStores): array
    {
        $stores = [];
        foreach ($sfStores as $sfStore) {
            $stores[] = Store::fromArray([
                'storeNumber' => $sfStore['Store']['id'],
                'city' => $sfStore['Store']['city'],
                'zipcode' => $sfStore['Store']['zip'],
                'street' => $sfStore['Store']['address'],
                'latitude' => $sfStore['Store']['lat'],
                'longitude' => $sfStore['Store']['lng'],
                'title' => $sfStore['Store']['more_info'],
                'text' => $sfStore['Store']['description'],
                'phone' => $sfStore['Store']['phone'],
                'fax' => $sfStore['Store']['fax'],
            ]);
        }

        return $stores;
    }

    private function createBrochure(array $brochureData): Brochure
    {
        $brochureDataToPass = [
            'integration' => $this->company,
            'pdfUrl' => $brochureData['pdf_url'],
            'brochureNumber' => $brochureData['number'],
            'title' => $brochureData['title'],
            'validFrom' => $brochureData['validFrom'],
            'validTo' => $brochureData['validTo'],
            'visibleFrom' => $brochureData['visibleFrom'],
            'trackingPixels' => $brochureData['trackingPixel'],
            'storeNumber' => $brochureData['stores'],
        ];

        return Brochure::fromArray($brochureDataToPass);
    }

    private function prepareBrochureData(array $brochureData, array $brochureDetail): array
    {
        $validFrom = $this->normalizeDate($brochureDetail['validity_start']);
        $validTo = $this->normalizeDate($brochureDetail['validity_end']);
        $validTo = (clone $validTo)->setTime(23, 59, 59);
        $visibleFrom = $this->normalizeDate($brochureDetail['visibility_start']);
        $dateTimeFormat = 'Y-m-d H:i:s';

        $brochureData['validFrom'] = $validFrom->format($dateTimeFormat);
        $brochureData['validTo'] = $validTo->format($dateTimeFormat);
        $brochureData['visibleFrom'] = $visibleFrom->format($dateTimeFormat);
        $brochureData['number'] = $brochureData['id'];
        $brochureData['trackingPixel'] = $brochureDetail['tracking_pixel'] ?? '';

        return $brochureData;
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
