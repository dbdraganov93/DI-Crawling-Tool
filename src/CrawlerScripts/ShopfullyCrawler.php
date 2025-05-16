<?php
namespace App\CrawlerScripts;

use App\Entity\Company;
use App\Entity\ShopfullyLog;
use App\Service\IprotoService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ShopfullyService;

class ShopfullyCrawler
{
    private EntityManagerInterface $em;
    private IprotoService $iprotoService;
    private ShopfullyService $shopfullyService;
    public function __construct(EntityManagerInterface $em, ShopfullyService $shopfullyService, IprotoService $iprotoService,)
    {
        $this->em = $em;
        $this->shopfullyService = $shopfullyService;
        $this->iprotoService = $iprotoService;
    }

    public function crawl(array $data): void
    {
        /** @var Company $company */
        $company = $data['company'];
        $locale = $data['locale'];
        $brochures = $data['numbers'];

        foreach ($brochures as $brochure) {
            $brochureData = $this->shopfullyService->getBrochure($brochure['number'], $locale);
dd($brochureData);
        }

        $log = new ShopfullyLog();
        $log->setCompanyName($company->getName());
        $log->setIprotoId($company->getIprotoId());
        $log->setLocale($locale);
        $log->setData($brochures);
        $log->setCreatedAt(new \DateTime());

        $this->em->persist($log);
        $this->em->flush();
    }
}
