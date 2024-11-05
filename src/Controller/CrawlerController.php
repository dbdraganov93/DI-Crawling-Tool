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

#[Route('/crawler')]
final class CrawlerController extends AbstractController
{
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
        return $this->render('crawler/show.html.twig', [
            'crawler' => $crawler,
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
}
