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

        $filters = $this->resolveBrochureFilters($request);

        try {
            $brochures = $iprotoService->getBrochuresByOwnerAndCompany($ownerId, $companyId, $filters);

            return $this->json($brochures);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            $logger->error('Brochure API request failed.', [
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'filters' => $filters,
                'exception' => $exception,
            ]);

            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $exception) {
            $logger->error('Unexpected error while loading brochures.', [
                'companyId' => $companyId,
                'ownerId' => $ownerId,
                'filters' => $filters,
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
            $formatted = [];

            foreach ($stores as $store) {
                if (!is_array($store)) {
                    continue;
                }

                $storeNumber = $this->normalizeStoreString($store['storeNumber'] ?? $store['store_number'] ?? $store['number'] ?? null);

                if ($storeNumber === '' || isset($formatted[$storeNumber])) {
                    continue;
                }

                $name = $this->normalizeStoreString(
                    $store['name'] ?? $store['storeName'] ?? $store['description'] ?? $store['title'] ?? null,
                );
                $city = $this->normalizeStoreString($store['city'] ?? $store['cityName'] ?? $store['locationCity'] ?? null);
                $state = $this->normalizeStoreString($store['state'] ?? $store['region'] ?? $store['province'] ?? null);

                $label = $storeNumber;

                if ($name !== '' && $city !== '') {
                    $label = sprintf('%s — %s (%s)', $storeNumber, $name, $city);
                } elseif ($name !== '') {
                    $label = sprintf('%s — %s', $storeNumber, $name);
                } elseif ($city !== '') {
                    $label = sprintf('%s — %s', $storeNumber, $city);
                }

                if ($state !== '') {
                    if ($name !== '' && $city !== '') {
                        $label = sprintf('%s — %s (%s, %s)', $storeNumber, $name, $city, $state);
                    } elseif ($city !== '') {
                        $label = sprintf('%s — %s (%s)', $storeNumber, $city, $state);
                    } elseif ($name !== '') {
                        $label = sprintf('%s — %s (%s)', $storeNumber, $name, $state);
                    }
                }

                $formatted[$storeNumber] = [
                    'id' => $storeNumber,
                    'storeNumber' => $storeNumber,
                    'label' => $label,
                    'name' => $name,
                    'city' => $city,
                    'state' => $state,
                ];
            }

            return $this->json(array_values($formatted));
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
        $storeNumbers = $data['stores'] ?? ($data['selectedStores'] ?? []);

        if (!is_array($brochureIds)) {
            $brochureIds = [];
        }

        if (!is_array($storeNumbers)) {
            $storeNumbers = [];
        }

        try {
            switch ($action) {
                case BrochureActionService::ACTION_DUPLICATE_PER_STORE:
                    $result = $brochureActionService->duplicateBrochuresPerStore($companyId, $ownerId, $brochureIds);
                    break;
                case BrochureActionService::ACTION_DUPLICATE_SELECTED_STORES:
                    $result = $brochureActionService->duplicateBrochuresPerSelectedStores(
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

    /**
     * @return array{deletedFilter: string, timeConstraints: array<int, string>}
     */
    private function resolveBrochureFilters(Request $request): array
    {
        $filters = [
            'deletedFilter' => 'active',
            'timeConstraints' => ['current', 'upcoming'],
        ];

        $deletedFilterRaw = strtolower(trim((string) $request->query->get('deletedFilter', '')));
        if ($deletedFilterRaw === 'deleted') {
            $filters['deletedFilter'] = 'deleted';
        } elseif ($deletedFilterRaw === 'all') {
            $filters['deletedFilter'] = 'all';
        } else {
            $filters['deletedFilter'] = 'active';
        }

        $timeConstraintParam = $request->query->all('timeConstraint');
        $timeConstraints = $this->normalizeTimeConstraintFilters($timeConstraintParam);

        if (!empty($timeConstraints)) {
            $filters['timeConstraints'] = $timeConstraints;
        } elseif ($request->query->has('timeConstraint')) {
            $filters['timeConstraints'] = [];
        }

        return $filters;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizeTimeConstraintFilters(mixed $raw): array
    {
        $allowed = ['current', 'upcoming', 'past'];
        $normalized = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (is_int($key)) {
                    $nested = $this->normalizeTimeConstraintFilters($value);
                    foreach ($nested as $item) {
                        if (!in_array($item, $normalized, true)) {
                            $normalized[] = $item;
                        }
                    }
                    continue;
                }

                if (!$this->isTruthy($value)) {
                    continue;
                }

                $constraint = strtolower(trim((string) $key));
                if ($constraint === '' || !in_array($constraint, $allowed, true) || in_array($constraint, $normalized, true)) {
                    continue;
                }

                $normalized[] = $constraint;
            }

            return $normalized;
        }

        if (is_string($raw)) {
            $constraint = strtolower(trim($raw));
            if ($constraint !== '' && in_array($constraint, $allowed, true) && !in_array($constraint, $normalized, true)) {
                $normalized[] = $constraint;
            }
        }

        return $normalized;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return !in_array($normalized, ['', '0', 'false', 'off', 'no'], true);
        }

        return $value !== null;
    }

    private function normalizeStoreString(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', $trimmed);

        return is_string($normalized) && $normalized !== '' ? $normalized : $trimmed;
    }
}
