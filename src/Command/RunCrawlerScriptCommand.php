<?php
// src/Command/RunCrawlerScriptCommand.php

namespace App\Command;

use App\CrawlerScripts\CrawlerScriptInterface;
use App\Service\FtpService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunCrawlerScriptCommand extends Command
{
    protected static $defaultName = 'app:crawler:run-script';
    private FtpService $ftpService;

    public function __construct(FtpService $ftpService)
    {
        parent::__construct();
        $this->ftpService = $ftpService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run a specific script associated with a Crawler')
            ->addArgument('scriptName', InputArgument::REQUIRED, 'The name of the script class to execute')
            ->addArgument('companyId', InputArgument::REQUIRED, 'The company ID to pass to the script');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scriptName = $input->getArgument('scriptName');
        $companyId = (int)$input->getArgument('companyId');

        $className = "App\\CrawlerScripts\\{$scriptName}";
        if (!class_exists($className)) {
            $output->writeln("Script '{$scriptName}' not found.");
            return Command::FAILURE;
        }

        $scriptInstance = new $className();
        if (!$scriptInstance instanceof CrawlerScriptInterface) {
            $output->writeln("Script '{$scriptName}' does not implement CrawlerScriptInterface.");
            return Command::FAILURE;
        }

        $scriptInstance->crawl($companyId, $this->ftpService);
        $output->writeln("Crawl completed for script '{$scriptName}' and company ID {$companyId}.");
        return Command::SUCCESS;
    }
}
