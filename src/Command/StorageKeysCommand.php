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

#[AsCommand(name: 'storage:keys', description: 'List keys matching a pattern')]
class StorageKeysCommand extends Command
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
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of keys to display', 100)
            ->addOption('show-ttl', 't', InputOption::VALUE_NONE, 'Show TTL for each key')
            ->addOption('show-type', null, InputOption::VALUE_NONE, 'Show type for each key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $client = $this->storageClient->getClient();

        $pattern = $input->getOption('pattern');
        $limit = (int)$input->getOption('limit');
        $showTtl = $input->getOption('show-ttl');
        $showType = $input->getOption('show-type');

        $io->title(sprintf('Keys matching pattern: %s', $pattern));

        $keys = [];
        $count = 0;

        try {
            foreach ($this->storageClient->scan($pattern, 1000) as $keyBatch) {
                foreach ($keyBatch as $key) {
                    if ($count >= $limit) {
                        break 2;
                    }
                    $keys[] = $key;
                    $count++;
                }
            }
        } catch (\Exception $e) {
            $io->error('Failed to scan keys: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($keys)) {
            $io->warning('No keys found matching the pattern.');
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($keys as $key) {
            $row = [$key];

            if ($showType) {
                try {
                    $type = $client->type($key);
                    $row[] = $type;
                } catch (\Exception $e) {
                    $row[] = 'error';
                }
            }

            if ($showTtl) {
                try {
                    $ttl = $this->storageClient->ttl($key);
                    $row[] = $this->formatTtl($ttl);
                } catch (\Exception $e) {
                    $row[] = 'error';
                }
            }

            $tableData[] = $row;
        }

        $headers = ['Key'];
        if ($showType) {
            $headers[] = 'Type';
        }
        if ($showTtl) {
            $headers[] = 'TTL';
        }

        $io->table($headers, $tableData);

        $io->success(sprintf('Found %d key(s)%s', $count, $count >= $limit ? ' (limited to ' . $limit . ')' : ''));

        return Command::SUCCESS;
    }

    private function formatTtl(int $ttl): string
    {
        if ($ttl === -2) {
            return 'key does not exist';
        }
        if ($ttl === -1) {
            return 'no expiration';
        }
        if ($ttl < 60) {
            return $ttl . 's';
        }
        if ($ttl < 3600) {
            return round($ttl / 60, 1) . 'm';
        }
        if ($ttl < 86400) {
            return round($ttl / 3600, 1) . 'h';
        }
        return round($ttl / 86400, 1) . 'd';
    }
}
