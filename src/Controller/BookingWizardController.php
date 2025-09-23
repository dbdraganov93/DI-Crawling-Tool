<?php

namespace App\Controller;

use App\Form\BookingWizardForm;
use App\Service\BookingManagementService;
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
