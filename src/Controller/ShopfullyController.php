<?php

// src/Controller/FormController.php
namespace App\Controller;

use App\Form\ShopfullyForm;
use App\Service\IprotoService;
use App\Service\LocaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\SimpleFormType;
use App\CrawlerScripts\ShopfullyCrawler;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ShopfullyLogRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Service\ShopfullyService;

class ShopfullyController extends AbstractController
{
    private ShopfullyService $shopfullyService;
    private LocaleService $localeService;
    private IprotoService $iprotoService;
    public function __construct(ShopfullyCrawler $crawler, IprotoService $iprotoService, ShopfullyService $shopfullyService, LocaleService $localeService)
    {
        $this->shopfullyService = $shopfullyService;
        $this->crawler = $crawler;
        $this->iprotoService = $iprotoService;
        $this->localeService = $localeService;
    }

    #[Route('/shopfully-wizard', name: 'app_shopfully_wizard')]
    public function index(Request $request, ShopfullyLogRepository $logRepo): Response
    {
        $form = $this->createForm(ShopfullyForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $data['timezone'] = $form->get('timezone')->getData();
            $this->crawler->crawl($data);
            $this->addFlash('success', 'Form submitted successfully!');
        }

        $logs = $logRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('shopfully/wizard.html.twig', [
            'form' => $form->createView(),
            'logs' => $logs,
        ]);
    }

    #[Route('/api/shopfully/brochure', name: 'api_shopfully_brochure_data', methods: ['GET'])]
    public function getBrochureData(Request $request): JsonResponse
    {
        $brochureNumber = $request->query->get('brochure_number');
        $locale = $request->query->get('locale');

        if (!$brochureNumber || !$locale) {
            throw new BadRequestHttpException('Missing brochure_number or locale');
        }

        try {
            $data = $this->shopfullyService->fetchBrochureData($brochureNumber, $locale);

            $flyers = $data['data'] ?? [];
            $response = [];

            foreach ($flyers as $entry) {
                if (isset($entry['Flyer'])) {
                    $response[] = [
                        'start_date' => $entry['Flyer']['start_date'] ?? null,
                        'end_date' => $entry['Flyer']['end_date'] ?? null,
                    ];
                }
            }

            return new JsonResponse($response);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/shopfully/locale', name: 'api_shopfully_locale_by_owner', methods: ['GET'])]
    public function getLocaleByOwner(Request $request): JsonResponse
    {
        $ownerId = (int) $request->query->get('ownerId');

        if (!$ownerId) {
            return new JsonResponse(['error' => 'Missing ownerId'], 400);
        }

        $locales = LocaleService::LOCALE;

        $locale = $locales[$ownerId] ?? null;

        if (!$locale) {
            return new JsonResponse(['error' => 'Locale not found'], 404);
        }

        return new JsonResponse(['locale' => $locale]);
    }

}
