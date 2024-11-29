<?php

namespace App\Command;

use App\CrawlerScripts\CrawlerScriptInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:crawler:run-script',
    description: 'Run a crawler script for a specific company.',
)]
class RunCrawlerScriptCommand extends Command
{
    private iterable $crawlerScripts;

    public function __construct(iterable $crawlerScripts)
    {
        parent::__construct();
        $this->crawlerScripts = $crawlerScripts;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('scriptName', InputArgument::REQUIRED, 'The name of the crawler script to run.')
            ->addArgument('companyId', InputArgument::REQUIRED, 'The ID of the company.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scriptName = $input->getArgument('scriptName');
        $companyId = (int)$input->getArgument('companyId');

        foreach ($this->crawlerScripts as $script) {
            if ($script instanceof CrawlerScriptInterface && $script::class === "App\\CrawlerScripts\\$scriptName") {
                $output->writeln("Running crawl for company ID: {$companyId}");
                $result = $script->crawl($companyId);

                $output->writeln($result);
                return Command::SUCCESS;
            }
        }

        $output->writeln("Error: Crawler script {$scriptName} not found.");
        return Command::FAILURE;
    }
}
