<?php

namespace App\Controller;

use App\Entity\FlipifyImport;
use App\Repository\FlipifyImportRepository;
use App\Message\FlipifyImportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;
use Throwable;

final class FlipifyController extends AbstractController
{
    #[Route('/flipify', name: 'app_flipify', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        FlipifyImportRepository $repository,
        MessageBusInterface $messageBus,
    ): Response {
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
                'label' => 'Upload and analyse',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $pdfFile */
            $pdfFile = $form->get('pdfFile')->getData();
            $companyWebsite = $form->get('companyWebsite')->getData();

            if ($pdfFile) {
                $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/pdf';
                $storedFilename = sprintf('flipify-%s.%s', uniqid('', true), $pdfFile->guessExtension() ?: 'pdf');

                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                    $this->addFlash('error', 'Unable to access the upload directory.');

                    return $this->redirectToRoute('app_flipify');
                }

                try {
                    $pdfFile->move($uploadDirectory, $storedFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'There was an error while uploading the file. Please try again.');

                    return $this->redirectToRoute('app_flipify');
                }

                $originalName = $pdfFile->getClientOriginalName() ?: $storedFilename;

                $import = new FlipifyImport($originalName, $storedFilename, $companyWebsite ?: null);
                $entityManager->persist($import);
                $entityManager->flush();

                try {
                    $messageBus->dispatch(new FlipifyImportMessage($import->getId()));
                } catch (Throwable $exception) {
                    $import->markFailed('Failed to queue analysis: ' . $exception->getMessage());
                    $entityManager->flush();
                    @unlink(sprintf('%s/%s', $uploadDirectory, $storedFilename));

                    $this->addFlash('error', 'The PDF was uploaded but could not be queued for analysis. Please try again.');

                    return $this->redirectToRoute('app_flipify');
                }

                $this->addFlash('success', 'PDF uploaded successfully. Analysis has been queued and will run shortly.');

                return $this->redirectToRoute('app_flipify');
            }
        }

        return $this->render('flipify/index.html.twig', [
            'form' => $form->createView(),
            'imports' => $repository->findLatest(),
        ]);
    }

    #[Route('/flipify/export/{id}', name: 'app_flipify_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function export(FlipifyImport $import): Response
    {
        $payload = [
            'uploaded_at' => $import->getCreatedAt()->format(DATE_ATOM),
            'original_filename' => $import->getOriginalFilename(),
            'company_website' => $import->getCompanyWebsite(),
            'products' => $import->getProducts(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s.json"', pathinfo($import->getStoredFilename(), PATHINFO_FILENAME)));

        return $response;
    }
}
