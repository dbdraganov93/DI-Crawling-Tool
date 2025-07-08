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
use App\Repository\ShopfullyPresetRepository;
use App\Entity\ShopfullyPreset;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Service\ShopfullyService;
use Symfony\Component\Process\Process;

class ShopfullyController extends AbstractController
{
    private ShopfullyService $shopfullyService;
    private LocaleService $localeService;
    private IprotoService $iprotoService;
    private ShopfullyCrawler $crawler;
    public function __construct(ShopfullyCrawler $crawler, IprotoService $iprotoService, ShopfullyService $shopfullyService, LocaleService $localeService)
    {
        $this->shopfullyService = $shopfullyService;
        $this->crawler = $crawler;
        $this->iprotoService = $iprotoService;
        $this->localeService = $localeService;
    }

    #[Route('/shopfully-wizard', name: 'app_shopfully_wizard')]
    public function index(
        Request $request,
        ShopfullyLogRepository $logRepo,
        ShopfullyPresetRepository $presetRepo,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(ShopfullyForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $data['timezone'] = $form->get('timezone')->getData();
            $preset = new ShopfullyPreset();
            $preset->setCreatedAt(new \DateTime());
            $preset->setScheduledAt(new \DateTime());
            $preset->setData($data);
            $preset->setStatus('pending');
            $em->persist($preset);
            $em->flush();
            $console = $this->getParameter('kernel.project_dir') . '/bin/console';
            $cmd = sprintf(
                'php %s app:shopfully:worker %d >> var/log/preset.log 2>&1',
                $console,
                $preset->getId()
            );
            $process = Process::fromShellCommandline(
                $cmd,
                $this->getParameter('kernel.project_dir')
            );
            $env = $_ENV;
            // Ensure AWS credentials file is available for the worker process
            $env['AWS_SHARED_CREDENTIALS_FILE'] = $env['AWS_SHARED_CREDENTIALS_FILE'] ?? '/var/www/.aws/credentials';
            $process->setEnv($env);
            $process->disableOutput();
            $process->setTimeout(null);
            $process->start();
            $this->addFlash('success', 'Job queued and started!');
        }

        $logs = $logRepo->findBy([], ['createdAt' => 'DESC']);
        $presets = $presetRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('shopfully/wizard.html.twig', [
            'form' => $form->createView(),
            'logs' => $logs,
            'presets' => $presets,
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
                    $clickouts = $this->shopfullyService->fetchBrochureClickouts($brochureNumber, $locale);
                    $response[] = [
                        'start_date' => $entry['Flyer']['start_date'] ?? null,
                        'end_date' => $entry['Flyer']['end_date'] ?? null,
                        'clickouts_count' => count($clickouts),
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

    #[Route('/api/shopfully/preview', name: 'api_shopfully_preview', methods: ['GET'])]
    public function getBrochurePreview(Request $request): JsonResponse
    {
        $brochureNumber = $request->query->get('brochure_number');
        $locale = $request->query->get('locale');

        if (!$brochureNumber || !$locale) {
            throw new BadRequestHttpException('Missing brochure_number or locale');
        }

        try {
            $brochureData = $this->shopfullyService->fetchBrochureData($brochureNumber, $locale);
            $publicationId = $brochureData['publication_id'] ?? null;
            $publicationData = $publicationId ? $this->shopfullyService->fetchPublicationData($publicationId, $locale) : [];
            $pdfUrl = $publicationData['data'][0]['Publication']['pdf_url'] ?? null;
            $clickouts = $this->shopfullyService->fetchBrochureClickouts($brochureNumber, $locale);

            return new JsonResponse([
                'pdf_url' => $pdfUrl,
                'clickouts' => $clickouts,
            ]);
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
