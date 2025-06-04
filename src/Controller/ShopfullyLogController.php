<?php

namespace App\Controller;

use App\Repository\ShopfullyLogRepository;
use App\Service\IprotoService;
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


}