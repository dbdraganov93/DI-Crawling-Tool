<?php
// src/Controller/CrawlerController.php

namespace App\Controller;

use App\Entity\Crawler;
use App\Form\CrawlerType;
use App\Repository\CrawlerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
#[Route('/crawler')]
final class CrawlerController extends AbstractController
{
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }
    #[Route(name: 'app_crawler_index', methods: ['GET'])]
    public function index(CrawlerRepository $crawlerRepository): Response
    {
        return $this->render('crawler/index.html.twig', [
            'crawlers' => $crawlerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_crawler_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $crawler = new Crawler();
        $form = $this->createForm(CrawlerType::class, $crawler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set the author to the currently logged-in user
            $crawler->setAuthor($this->getUser());

            $entityManager->persist($crawler);
            $entityManager->flush();

            // Schedule the cron job
            $this->scheduleCronJob($crawler);

            return $this->redirectToRoute('app_crawler_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('crawler/new.html.twig', [
            'crawler' => $crawler,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_crawler_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Crawler $crawler, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CrawlerType::class, $crawler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Remove the old cron job before setting the new one
            $this->removeCronJob($crawler);

            $entityManager->flush();

            // Schedule the new cron job
            $this->scheduleCronJob($crawler);

            return $this->redirectToRoute('app_crawler_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('crawler/edit.html.twig', [
            'crawler' => $crawler,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_crawler_show', methods: ['GET'])]
    public function show(Crawler $crawler): Response
    {
        $csrfToken = $this->csrfTokenManager->getToken('run_crawler_' . $crawler->getId())->getValue();

        return $this->render('crawler/show.html.twig', [
            'crawler' => $crawler,
            'csrf_token' => $csrfToken,
        ]);
    }

    #[Route('/{id}', name: 'app_crawler_delete', methods: ['POST'])]
    public function delete(Request $request, Crawler $crawler, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$crawler->getId(), $request->getPayload()->getString('_token'))) {
            // Remove the associated cron job
            $this->removeCronJob($crawler);

            $entityManager->remove($crawler);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_crawler_index', [], Response::HTTP_SEE_OTHER);
    }

    private function scheduleCronJob(Crawler $crawler): void
    {
        $cronExpression = $crawler->getCron();
        $scriptName = $crawler->getScript();

        // Define the full path to your PHP binary and Symfony console
        $phpBinary = '/usr/bin/php'; // Adjust this path if PHP is located elsewhere
        $consolePath = __DIR__ . '/../../bin/console';

        // Define the command to run the Symfony command for this script
        $command = "{$phpBinary} {$consolePath} app:crawler:run-script {$scriptName}";

        // Combine the cron expression with the command
        $cronJob = "{$cronExpression} {$command} > /dev/null 2>&1";

        // Get current cron jobs, add the new one, and update the crontab
        $currentCrontab = shell_exec('crontab -l');
        $newCrontab = $currentCrontab . PHP_EOL . $cronJob . PHP_EOL;
        shell_exec("echo '$newCrontab' | crontab -");
    }

    private function removeCronJob(Crawler $crawler): void
    {
        $cronExpression = $crawler->getCron();
        $scriptName = $crawler->getScript();

        // Define the full path to your PHP binary and Symfony console
        $phpBinary = '/usr/bin/php';
        $consolePath = __DIR__ . '/../../bin/console';

        // Define the command
        $command = "{$phpBinary} {$consolePath} app:crawler:run-script {$scriptName}";
        $cronJob = "{$cronExpression} {$command} > /dev/null 2>&1";

        // Get the current cron jobs
        $currentCrontab = shell_exec('crontab -l');

        // Remove the specified cron job
        $updatedCrontab = str_replace($cronJob . PHP_EOL, '', $currentCrontab);

        // Update the crontab
        shell_exec("echo '$updatedCrontab' | crontab -");
    }

    #[Route('/crawler/run/{id}', name: 'app_crawler_run', methods: ['POST'])]
    public function run(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('run' . $id, $token)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        // Find the crawler by ID
        $crawler = $entityManager->getRepository(Crawler::class)->find($id);

        if (!$crawler) {
            return new JsonResponse(['success' => false, 'error' => 'Crawler not found'], 404);
        }

        // Run the crawler command
        $scriptName = $crawler->getScript();
        $companyId = $crawler->getCompanyId();

        // Use Symfony Process to execute the command
        $process = new Process([
            'php',
            'bin/console',
            'app:crawler:run-script',
            $scriptName,
            $companyId
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            return new JsonResponse([
                'success' => false,
                'error' => $process->getErrorOutput(),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'output' => $process->getOutput(),
        ]);
    }

    #[Route('/crawler/run/{id}', name: 'app_crawler_run', methods: ['POST'])]
    public function runCrawler(Crawler $crawler, KernelInterface $kernel): JsonResponse
    {
        $companyId = $crawler->getCompanyId()->getId();
        $scriptName = $crawler->getScript();

        // Get the full path to the console file
        $consolePath = $kernel->getProjectDir() . '/bin/console';

        // Add `--no-debug` to suppress debug information
        $command = sprintf(
            'php %s app:crawler:run-script %s %d --no-debug 2>&1',
            escapeshellarg($consolePath),
            escapeshellarg($scriptName),
            $companyId
        );

        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $errorOutput = implode("\n", $output);
            return new JsonResponse([
                'success' => false,
                'error' => "Command execution failed: {$errorOutput}"
            ]);
        }

        // Replace file path with a URL for download
        $csvPath = end($output); // Assume CSV path is the last line of output
        $csvUrl = str_replace($kernel->getProjectDir() . '/public', '', $csvPath);

        return new JsonResponse([
            'success' => true,
            'output' => implode("\n", array_slice($output, 0, -1)), // Exclude the last line (CSV path)
            'downloadUrl' => $csvUrl,
        ]);
    }

}
