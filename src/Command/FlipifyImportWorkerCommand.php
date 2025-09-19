<?php

namespace App\Command;

use App\Repository\FlipifyImportRepository;
use App\Service\Flipify\FlipifyImportProcessor;
use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'monolog.logger.flipify')]
        private readonly LoggerInterface $logger,
        #[Autowire('%flipify.processing_timeout_seconds%')]
        private readonly int $processingTimeoutSeconds,
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

        $this->logger->info('Flipify worker initialised.', [
            'mode' => $id !== null ? 'single' : 'daemon',
            'sleepSeconds' => $sleepSeconds,
        ]);

        if ($id !== null) {
            $importId = (int) $id;
            $output->writeln(sprintf('<info>Processing Flipify import %d...</info>', $importId));

            $result = $this->processor->process($importId);

            $this->logger->info('Flipify worker finished single-run execution.', [
                'importId' => $importId,
                'result' => $result ? 'processed' : 'skipped',
            ]);

            return $result ? Command::SUCCESS : Command::FAILURE;
        }

        $output->writeln('<info>Flipify worker started. Press Ctrl+C to stop.</info>');

        $this->logger->info('Flipify worker entering processing loop.');

        while (!$this->shouldStop) {
            $importId = $this->repository->findNextPendingId($this->resolveStalledBefore());

            if ($importId === null) {
                $output->writeln('<comment>No pending Flipify imports. Sleeping...</comment>');
                $this->logger->debug('No pending Flipify imports found.', [
                    'sleepSeconds' => $sleepSeconds,
                ]);
                sleep($sleepSeconds);
                continue;
            }

            $output->writeln(sprintf('<info>Processing Flipify import %d...</info>', $importId));
            $this->logger->info('Flipify worker picked up import.', [
                'importId' => $importId,
            ]);

            $result = $this->processor->process($importId);

            $this->logger->info('Flipify worker finished processing import.', [
                'importId' => $importId,
                'result' => $result ? 'processed' : 'skipped',
            ]);
        }

        $output->writeln('<info>Flipify worker stopped.</info>');

        $this->logger->info('Flipify worker stopped gracefully.');

        return Command::SUCCESS;
    }

    private function resolveStalledBefore(): ?DateTimeImmutable
    {
        if ($this->processingTimeoutSeconds <= 0) {
            return null;
        }

        $seconds = max(1, $this->processingTimeoutSeconds);

        return (new DateTimeImmutable())->sub(new DateInterval(sprintf('PT%dS', $seconds)));
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
