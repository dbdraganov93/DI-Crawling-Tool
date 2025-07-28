<?php

namespace App\Command;

use App\CrawlerScripts\ShopfullyCrawler;
use App\Repository\ShopfullyPresetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:shopfully:worker',
    aliases: ['app:shopfully:execute-presets'],
    description: 'Run a Shopfully preset in a dedicated worker',
)]
class ShopfullyWorkerCommand extends Command
{
    public function __construct(
        private ShopfullyPresetRepository $presetRepository,
        private ShopfullyCrawler $crawler,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Preset ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        if ($id) {
            $preset = $this->presetRepository->find((int) $id);
            if (!$preset) {
                $output->writeln("<error>Preset $id not found.</error>");
                return Command::FAILURE;
            }

            $output->writeln(sprintf('<info>Starting preset %d...</info>', $preset->getId()));
            $this->logger->info('Preset {id} started', ['id' => $preset->getId()]);
            $this->runPreset($preset, $output);
            $output->writeln(sprintf('<info>Preset %d finished</info>', $preset->getId()));
            $this->logger->info('Preset {id} finished', ['id' => $preset->getId()]);
            return Command::SUCCESS;
        }

        while (true) {
            $preset = $this->presetRepository->findOneBy(['status' => 'pending'], ['createdAt' => 'ASC']);
            if ($preset) {
                $output->writeln(sprintf('<info>Starting preset %d...</info>', $preset->getId()));
                $this->logger->info('Preset {id} started', ['id' => $preset->getId()]);
                $this->runPreset($preset, $output);
                $output->writeln(sprintf('<info>Preset %d finished</info>', $preset->getId()));
                $this->logger->info('Preset {id} finished', ['id' => $preset->getId()]);
            } else {
                $output->writeln('<info>No pending presets. Sleeping...</info>');
                $this->logger->info('No pending presets. Worker sleeping.');
                sleep(10);
            }
        }

        return Command::SUCCESS;
    }

    private function runPreset($preset, OutputInterface $output): void
    {
        $preset->setStatus('running');
        $preset->setExecutedAt(new \DateTime());
        $this->em->flush();

        try {
            $this->crawler->setAuthor($preset->getAuthor());
            $this->crawler->crawl($preset->getData());
            $preset->setStatus('finished');
            $preset->setErrorMessage(null);
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Preset %d failed: %s</error>', $preset->getId(), $e->getMessage()));
            $this->logger->error('Preset {id} failed: {error}', [
                'id' => $preset->getId(),
                'error' => $e->getMessage(),
            ]);
            $preset->setStatus('failed');
            $preset->setErrorMessage($e->getMessage());
        }

        $this->em->flush();
    }
}
