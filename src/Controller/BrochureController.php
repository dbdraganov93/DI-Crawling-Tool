<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BrochureLinkerService;
use App\Entity\BrochureJob;
use App\Repository\BrochureJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class BrochureController extends AbstractController
{
    public function __construct(private BrochureLinkerService $linker)
    {
    }

    #[Route('/brochure/upload', name: 'brochure_upload_form', methods: ['GET'])]
    public function uploadForm(BrochureJobRepository $repo): Response
    {
        $jobs = $repo->findBy([], ['createdAt' => 'DESC']);
        return $this->render('brochure/wizard.html.twig', ['jobs' => $jobs]);
    }

    #[Route('/brochure/upload', name: 'brochure_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $file = $request->files->get('pdf');
            if (!$file) {
                return new JsonResponse(['error' => 'No PDF provided'], Response::HTTP_BAD_REQUEST);
            }

            $website = $request->request->get('website');
            $prefix = $request->request->get('prefix');
            $suffix = $request->request->get('suffix');

            $dir = $this->getParameter('kernel.project_dir') . '/public/pdf';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $filename = uniqid('brochure_', true) . '.pdf';
            $file->move($dir, $filename);
            $path = $dir . '/' . $filename;

            $job = new BrochureJob();
            $job->setPdfPath($path);
            $job->setSearchWebsite($website ?: null);
            $job->setLinkPrefix($prefix ?: null);
            $job->setLinkSuffix($suffix ?: null);
            $em->persist($job);
            $em->flush();

            $console = $this->getParameter('kernel.project_dir') . '/bin/console';
            $logDir = $this->getParameter('kernel.project_dir') . '/var/log';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            $cmd = sprintf('php %s app:brochure:worker %d >> var/log/brochure-job.log 2>&1', $console, $job->getId());
            $process = Process::fromShellCommandline($cmd, $this->getParameter('kernel.project_dir'));
            $process->disableOutput();
            $process->setTimeout(null);
            $process->start();

            return new JsonResponse(['job_id' => $job->getId()]);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Failed to queue brochure', 'details' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/brochure/status/{id}', name: 'brochure_status', methods: ['GET'])]
    public function status(int $id, BrochureJobRepository $repo): JsonResponse
    {
        $job = $repo->find($id);
        if (!$job) {
            return new JsonResponse(['error' => 'Job not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'status' => $job->getStatus(),
            'error' => $job->getErrorMessage(),
        ]);
    }

    #[Route('/brochure/download/{id}/{type}', name: 'brochure_download', requirements: ['type' => 'pdf|json'], methods: ['GET'])]
    public function download(int $id, string $type, BrochureJobRepository $repo): Response
    {
        $job = $repo->find($id);
        if (!$job) {
            return new Response('Job not found', 404);
        }

        $path = $type === 'pdf' ? $job->getResultPdf() : $job->getResultJson();
        if (!$path || !file_exists($path)) {
            return new Response('File not ready', 404);
        }

        return $this->file($path);
    }

    #[Route('/brochure/wizard', name: 'brochure_wizard', methods: ['GET'])]
    public function wizard(BrochureJobRepository $repo): Response
    {
        $jobs = $repo->findBy([], ['createdAt' => 'DESC']);
        return $this->render('brochure/wizard.html.twig', ['jobs' => $jobs]);
    }

    #[Route('/brochure/edit/{id}', name: 'brochure_edit', methods: ['GET'])]
    public function edit(int $id, BrochureJobRepository $repo): Response
    {
        $job = $repo->find($id);
        if (!$job || !$job->getResultPdf()) {
            throw $this->createNotFoundException('Job not found');
        }

        $publicDir = $this->getParameter('kernel.project_dir') . '/public';
        $pdfUrl = str_replace($publicDir, '', $job->getResultPdf());
        $jsonUrl = $job->getResultJson() ? str_replace($publicDir, '', $job->getResultJson()) : null;

        return $this->render('brochure/editor.html.twig', [
            'pdfUrl' => $pdfUrl,
            'jsonUrl' => $jsonUrl,
        ]);
    }
}
