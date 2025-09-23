<?php

namespace App\Controller;

use App\Form\BookingWizardForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
