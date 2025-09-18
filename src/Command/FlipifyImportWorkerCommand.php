<?php

namespace App\Command;

use App\Repository\FlipifyImportRepository;
use App\Service\Flipify\FlipifyImportProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SignalRegistry\SignalableCommandInterface;

#[AsCommand(
    name: 'app:flipify:worker',
    description: 'Continuously process pending Flipify brochure analyses.',
)]
final class FlipifyImportWorkerCommand extends Command implements SignalableCommandInterface
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly FlipifyImportRepository $repository,
        private readonly FlipifyImportProcessor $processor,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'Process a single Flipify import by its ID and exit.')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep when no imports are pending.', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sleepSeconds = max(1, (int) $input->getOption('sleep'));
        $id = $input->getArgument('id');

        if ($id !== null) {
            $importId = (int) $id;
            $output->writeln(sprintf('<info>Processing Flipify import %d...</info>', $importId));

            return $this->processor->process($importId) ? Command::SUCCESS : Command::FAILURE;
        }

        $output->writeln('<info>Flipify worker started. Press Ctrl+C to stop.</info>');

        while (!$this->shouldStop) {
            $importId = $this->repository->findNextPendingId();

            if ($importId === null) {
                $output->writeln('<comment>No pending Flipify imports. Sleeping...</comment>');
                sleep($sleepSeconds);
                continue;
            }

            $output->writeln(sprintf('<info>Processing Flipify import %d...</info>', $importId));
            $this->processor->process($importId);
        }

        $output->writeln('<info>Flipify worker stopped.</info>');

        return Command::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        $this->logger->info('Flipify worker received stop signal {signal}.', ['signal' => $signal]);
        $this->shouldStop = true;
    }
}
