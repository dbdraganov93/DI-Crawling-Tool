<?php

namespace App\Command;

use App\Entity\BrochureJob;
use App\Repository\BrochureJobRepository;
use App\Service\BrochureLinkerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:brochure:worker',
    description: 'Process Brochure jobs in background',
)]
class BrochureWorkerCommand extends Command
{
    public function __construct(
        private BrochureJobRepository $repository,
        private BrochureLinkerService $linker,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Job ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        if ($id) {
            $job = $this->repository->find((int)$id);
            if (!$job) {
                $output->writeln("<error>Job $id not found</error>");
                return Command::FAILURE;
            }
            $this->runJob($job, $output);
            return Command::SUCCESS;
        }

        while (true) {
            $job = $this->repository->findOneBy(['status' => 'pending'], ['createdAt' => 'ASC']);
            if ($job) {
                $this->runJob($job, $output);
            } else {
                $output->writeln('<info>No jobs. Sleeping...</info>');
                sleep(5);
            }
        }

        return Command::SUCCESS;
    }

    private function runJob(BrochureJob $job, OutputInterface $output): void
    {
        $job->setStatus('running');
        $this->em->flush();

        try {
            $data = $this->linker->process($job->getPdfPath(), $job->getSearchWebsite());
            $job->setStatus('finished');
            $job->setResultPdf($data['annotated']);
            $job->setResultJson($data['json']);
            $job->setErrorMessage(null);
            $this->logger->info('Brochure job {id} finished', ['id' => $job->getId()]);
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Job %d failed: %s</error>', $job->getId(), $e->getMessage()));
            $this->logger->error('Brochure job {id} failed: {error}', ['id' => $job->getId(), 'error' => $e->getMessage()]);
            $job->setStatus('failed');
            $job->setErrorMessage($e->getMessage());
        }

        $this->em->flush();
    }
}
