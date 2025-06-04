<?php

namespace App\Command;

use App\Service\IprotoTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// We need to set cron job at each 29 days with this command: php bin/console iproto:update-token
#[AsCommand(
    name: 'iproto:update-token',
    description: 'Updates the Auth0 token and stores it in the database.',
)]
class IprotoUpdateTokenCommand extends Command
{
    private IprotoTokenService $iprotoService;

    public function __construct(IprotoTokenService $iprotoService)
    {
        parent::__construct();
        $this->iprotoService = $iprotoService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->iprotoService->createToken();
            $output->writeln('<info>Token updated successfully.</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to update token: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
