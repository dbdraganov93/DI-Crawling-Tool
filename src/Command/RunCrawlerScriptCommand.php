<?php
// src/Command/RunCrawlerScriptCommand.php

namespace App\Command;

use App\CrawlerScripts\CrawlerScriptInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

//Example: php bin/console app:crawler:run-script SampleCrawlerScript 123
class RunCrawlerScriptCommand extends Command
{
    protected static $defaultName = 'app:crawler:run-script';

    private EntityManagerInterface $entityManager;
    private string $scriptNamespace = 'App\\CrawlerScripts\\'; // Namespace for crawler scripts

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
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
        $io = new SymfonyStyle($input, $output);
        $scriptName = $input->getArgument('scriptName');
        $companyId = (int)$input->getArgument('companyId');

        // Construct the full class name with namespace
        $className = $this->scriptNamespace . $scriptName;

        // Check if the class exists and implements the interface
        if (!class_exists($className)) {
            $io->error("Script class '{$className}' not found.");
            return Command::FAILURE;
        }

        // Instantiate the script class
        $scriptInstance = new $className();

        if (!$scriptInstance instanceof CrawlerScriptInterface) {
            $io->error("Script '{$scriptName}' does not implement CrawlerScriptInterface.");
            return Command::FAILURE;
        }

        // Execute the crawl method
        $io->success("Executing crawl method on script: {$scriptName} with companyId: {$companyId}");
        $scriptInstance->crawl($companyId);

        return Command::SUCCESS;
    }
}
