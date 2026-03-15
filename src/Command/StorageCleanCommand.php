<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Command;

use PhilippHermes\StorageBundle\Client\StorageClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'storage:clean', description: 'Clears storage')]
class StorageCleanCommand extends Command
{
    public function __construct(
        private readonly StorageClientInterface $storageClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('pattern', 'p', InputOption::VALUE_OPTIONAL, 'Key pattern to match (default: *)', '*')
            ->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Number of keys to scan per iteration', 1000)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $pattern = $input->getOption('pattern');
        $batch = (int)$input->getOption('batch');
        $force = $input->getOption('force');

        if (!$force) {
            $confirmed = $io->confirm(
                sprintf('Are you sure you want to delete all keys matching pattern "%s"?', $pattern),
                false
            );

            if (!$confirmed) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $io->info(sprintf('Deleting keys matching pattern: %s', $pattern));

        $deletedCount = 0;
        /** @var list<string> $keys */
        foreach ($this->storageClient->scan($pattern, $batch) as $keys) {
            $deletedCount += count($keys);
            $this->storageClient->deleteMultiple($keys);
        }

        $io->success(sprintf('Successfully deleted %d key(s).', $deletedCount));

        return Command::SUCCESS;
    }
}
