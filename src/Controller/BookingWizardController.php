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

    #[Route('/booking-wizard/api/stores', name: 'app_booking_wizard_stores', methods: ['GET'])]
    public function fetchStores(
        Request $request,
        IprotoService $iprotoService,
        LoggerInterface $logger,
    ): JsonResponse {
        $companyId = trim((string) $request->query->get('companyId', ''));

        if ($companyId === '') {
            return $this->json(['error' => 'Missing or invalid companyId parameter.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $stores = $iprotoService->getStoresByCompany($companyId);
            $normalizedStores = [];

            foreach ($stores as $store) {
                if (!is_array($store)) {
                    continue;
                }

                $storeNumber = $this->normalizeValue($store['storeNumber'] ?? $store['store_number'] ?? null);
                if ($storeNumber === '') {
                    continue;
                }

                $normalizedStores[] = [
                    'id' => $this->normalizeValue($store['id'] ?? null),
                    'storeNumber' => $storeNumber,
                    'title' => $this->normalizeValue($store['title'] ?? null),
                    'city' => $this->normalizeValue($store['city'] ?? null),
                    'postalCode' => $this->normalizeValue($store['postalCode'] ?? $store['postal_code'] ?? null),
                    'street' => $this->normalizeValue($store['street'] ?? null),
                    'streetNumber' => $this->normalizeValue($store['streetNumber'] ?? $store['street_number'] ?? null),
                ];
            }

            return $this->json($normalizedStores);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            $logger->error('Store API request failed.', [
                'companyId' => $companyId,
                'exception' => $exception,
            ]);

            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $exception) {
            $logger->error('Unexpected error while loading stores.', [
                'companyId' => $companyId,
                'exception' => $exception,
            ]);

            return $this->json(['error' => 'Unable to load stores.'], Response::HTTP_BAD_GATEWAY);
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
        $storeNumbersInput = $data['storeNumbers'] ?? [];

        if (!is_array($brochureIds)) {
            $brochureIds = [];
        }

        if (is_string($storeNumbersInput)) {
            $storeNumbers = array_filter(array_map('trim', explode(',', $storeNumbersInput)));
        } elseif (is_array($storeNumbersInput)) {
            $storeNumbers = array_values(array_filter(array_map(static function ($value) {
                if ($value instanceof \Stringable) {
                    $value = (string) $value;
                }

                if (is_scalar($value)) {
                    return trim((string) $value);
                }

                return '';
            }, $storeNumbersInput)));
        } else {
            $storeNumbers = [];
        }

        try {
            switch ($action) {
                case BrochureActionService::ACTION_DUPLICATE_PER_STORE:
                    $result = $brochureActionService->duplicateBrochuresPerStore($companyId, $ownerId, $brochureIds);
                    break;
                case BrochureActionService::ACTION_DUPLICATE_PER_SELECTED_STORE:
                    $result = $brochureActionService->duplicateBrochuresPerSelectedStore(
                        $companyId,
                        $ownerId,
                        $brochureIds,
                        $storeNumbers,
                    );
                    break;
                default:
                    return $this->json(['error' => 'Unsupported brochure action requested.'], Response::HTTP_BAD_REQUEST);
            }

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

            return $this->json(['error' => 'Unable to process brochure action.'], Response::HTTP_BAD_GATEWAY);
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

    private function normalizeValue(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
