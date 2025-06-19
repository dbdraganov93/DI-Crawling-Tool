<?php

namespace App\Controller;

use App\Repository\ShopfullyLogRepository;
use App\Service\IprotoService;
use App\CrawlerScripts\ShopfullyCrawler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShopfullyLogController extends AbstractController
{
    #[Route('/api/logs/{importId}/refresh', name: 'api_logs_refresh', methods: ['POST'])]
    public function refreshStatus(
        int $importId,
        ShopfullyLogRepository $logRepository,
        EntityManagerInterface $em,
        IprotoService $iprotoService
    ): JsonResponse {
        $log = $logRepository->findOneByImportId($importId);
        if (!$log) {
            return new JsonResponse(['success' => false, 'message' => 'Log not found'], 404);
        }

        try {
            $importStatus = $iprotoService->getImportStatus($importId);

            $log->setStatus($importStatus['status'] ?? 'unknown');
            $log->setNoticesCount($importStatus['noticesCount'] ?? 0);
            $log->setWarningsCount($importStatus['warningsCount'] ?? 0);
            $log->setErrorsCount($importStatus['errorsCount'] ?? 0);

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $log->getStatus(),
                'noticesCount' => $log->getNoticesCount(),
                'warningsCount' => $log->getWarningsCount(),
                'errorsCount' => $log->getErrorsCount(),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/logs/{id}/reimport', name: 'api_logs_reimport', methods: ['POST'])]
    public function reimport(
        int $id,
        ShopfullyLogRepository $logRepository,
        EntityManagerInterface $em,
        ShopfullyCrawler $crawler,
        \Psr\Log\LoggerInterface $logger
    ): JsonResponse {
        $log = $logRepository->find($id);
        if (!$log) {
            return new JsonResponse(['success' => false, 'message' => 'Log not found'], 404);
        }

        $data = $log->getData();
        if (!isset($data['numbers'])) {
            $numbers = [];
            foreach ((array) $data as $item) {
                if (is_array($item)) {
                    $numbers[] = [
                        'number' => $item['number'] ?? $item,
                        'tracking_pixel' => $item['tracking_pixel'] ?? '',
                        'validity_start' => $item['validity_start'] ?? null,
                        'validity_end' => $item['validity_end'] ?? null,
                        'visibility_start' => $item['visibility_start'] ?? null,
                    ];
                } else {
                    $numbers[] = [
                        'number' => $item,
                        'tracking_pixel' => '',
                        'validity_start' => null,
                        'validity_end' => null,
                        'visibility_start' => null,
                    ];
                }
            }
            $data = [
                'company' => $log->getIprotoId(),
                'locale' => $log->getLocale(),
                'numbers' => $numbers,
            ];
        }

        try {
            $crawler->crawl($data);
            $log->setReimportCount($log->getReimportCount() + 1);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'reimportCount' => $log->getReimportCount(),
            ]);
        } catch (\Throwable $e) {
            $logger->error('Reimport failed for log ' . $id . ': ' . $e->getMessage(), ['exception' => $e]);
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
