<?php

namespace App\Controller;

use App\Form\BookingWizardForm;
use App\Service\BookingManagementService;
use App\Service\BookingWizard\BrochureActionService;
use App\Service\IprotoService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingWizardController extends AbstractController
{
    #[Route('/booking-wizard', name: 'app_booking_wizard')]
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(BookingWizardForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Booking wizard form submitted successfully.');
        }

        return $this->render('booking/wizard.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/booking-wizard/api/brochures', name: 'app_booking_wizard_brochures', methods: ['GET'])]
    public function fetchBrochures(
        Request $request,
        IprotoService $iprotoService,
        LoggerInterface $logger,
    ): JsonResponse {
        $companyId = trim((string) $request->query->get('companyId', ''));
        $ownerId = trim((string) $request->query->get('ownerId', ''));

        if ($companyId === '' || $ownerId === '') {
            return $this->json(['error' => 'Missing or invalid ownerId or companyId parameter.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $brochures = $iprotoService->getBrochuresByOwnerAndCompany($ownerId, $companyId);

            return $this->json($brochures);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            $logger->error('Brochure API request failed.', [
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'exception' => $exception,
            ]);

            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $exception) {
            $logger->error('Unexpected error while loading brochures.', [
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'exception' => $exception,
            ]);

            return $this->json(['error' => 'Unable to load brochures.'], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/booking-wizard/api/brochure-actions', name: 'app_booking_wizard_brochure_actions', methods: ['POST'])]
    public function handleBrochureActions(
        Request $request,
        BrochureActionService $brochureActionService,
        LoggerInterface $logger,
    ): JsonResponse {
        $data = json_decode($request->getContent() ?? '', true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid request payload.'], Response::HTTP_BAD_REQUEST);
        }

        $action = trim((string) ($data['action'] ?? ''));
        $companyId = trim((string) ($data['companyId'] ?? ''));
        $ownerId = trim((string) ($data['ownerId'] ?? ''));
        $brochureIds = $data['brochureIds'] ?? [];

        if (!is_array($brochureIds)) {
            $brochureIds = [];
        }

        if ($action !== BrochureActionService::ACTION_DUPLICATE_PER_STORE) {
            return $this->json(['error' => 'Unsupported brochure action requested.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $brochureActionService->duplicateBrochuresPerStore($companyId, $ownerId, $brochureIds);

            return $this->json($result);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            $logger->error('Brochure action failed.', [
                'action' => $action,
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'brochureIds' => $brochureIds,
                'exception' => $exception,
            ]);

            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $logger->error('Unexpected error while processing brochure action.', [
                'action' => $action,
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'brochureIds' => $brochureIds,
                'exception' => $exception,
            ]);

            $message = trim($exception->getMessage());
            $error = $message !== ''
                ? sprintf('Unable to process brochure action: %s', $message)
                : 'Unable to process brochure action.';

            return $this->json(['error' => $error], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/booking-wizard/api/bookings', name: 'app_booking_wizard_bookings', methods: ['GET'])]
    public function fetchBookings(
        Request $request,
        BookingManagementService $bookingManagementService,
        LoggerInterface $logger,
    ): JsonResponse {
        $companyId = trim((string) $request->query->get('companyId', ''));
        $ownerId = trim((string) $request->query->get('ownerId', ''));
        $ownerId = $ownerId === '' ? null : $ownerId;

        if ($companyId == '') {
            return $this->json(['error' => 'Missing or invalid companyId parameter.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $bookings = $bookingManagementService->getCpcBookings($companyId, null, $ownerId);

            return $this->json($bookings);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            $logger->error('Booking API request failed.', [
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'exception' => $exception,
            ]);

            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $exception) {
            $logger->error('Unexpected error while loading CPC bookings.', [
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'exception' => $exception,
            ]);

            return $this->json(['error' => 'Unable to load CPC bookings.'], Response::HTTP_BAD_GATEWAY);
        }
    }
}
