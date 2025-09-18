<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;

final class FlipifyController extends AbstractController
{
    #[Route('/flipify', name: 'app_flipify', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('companyWebsite', UrlType::class, [
                'label' => 'Company website',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com',
                ],
            ])
            ->add('pdfFile', FileType::class, [
                'label' => 'PDF file',
                'mapped' => false,
                'attr' => ['accept' => '.pdf,application/pdf'],
                'constraints' => [
                    new File([
                        'mimeTypes' => ['application/pdf', 'application/x-pdf'],
                        'mimeTypesMessage' => 'Please upload a valid PDF document.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Upload PDF',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form->get('pdfFile')->getData();
            if ($pdfFile) {
                $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/pdf';
                $newFilename = sprintf('flipify-%s.%s', uniqid('', true), $pdfFile->guessExtension() ?: 'pdf');

                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                    $this->addFlash('error', 'Unable to access the upload directory.');

                    return $this->redirectToRoute('app_flipify');
                }

                try {
                    $pdfFile->move($uploadDirectory, $newFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'There was an error while uploading the file. Please try again.');

                    return $this->redirectToRoute('app_flipify');
                }

                $website = $form->get('companyWebsite')->getData();
                $message = 'PDF uploaded successfully.';
                if ($website) {
                    $message .= sprintf(' Company website: %s', $website);
                }
                $this->addFlash('success', $message);

                return $this->redirectToRoute('app_flipify');
            }
        }

        return $this->render('flipify/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
