<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Command;

use PhilippHermes\StorageBundle\Client\StorageClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'storage:read', description: 'Read storage data')]
class StorageReadCommand extends Command
{
    /**
     * @param StorageClientInterface $storageClient
     */
    public function __construct(
        private readonly StorageClientInterface $storageClient,
    )
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this
            ->addOption('keys', 'k', InputOption::VALUE_OPTIONAL, 'The keys to retrieve')
            ->addOption('pattern', 'p', InputOption::VALUE_OPTIONAL, 'The pattern to retrieve')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of keys to display', 100)
            ->addOption('info', 'i', InputOption::VALUE_NONE, 'Show metadata (TTL, type, size)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $keys = $input->getOption('keys');
        $pattern = $input->getOption('pattern');
        $limit = $input->getOption('limit');
        $info = $input->getOption('info');

        $data = [];

        if ($keys) {
            $data = $this->getByKeys(explode(',', $keys));
        } elseif ($pattern) {
            $data = $this->getByPattern($pattern, $limit);
        }

        if (!$data) {
            $io->error('No keys found or pattern provided.');

            return Command::FAILURE;
        }

        if ($info) {
            $data = $this->extendDataWithInfo($data);
        }

        $tableHeaders = ['key', 'value'];
        if ($info) {
            $tableHeaders[] = 'type';
            $tableHeaders[] = 'ttl';
        }

        $io->table($tableHeaders, $data);

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, array<string, string|int>>
     */
    protected function getByKeys(array $keys): array
    {
        if (!$keys) {
            return [];
        }

        $data = $this->storageClient->getMultiple($keys);

        $formattedData = [];
        foreach ($data as $key => $value) {
            $formattedData[$key] = [
                'key' => $key,
                'value' => is_array($value) ? (($json = json_encode($value)) === false ? '/' : $json) : ($value ?? '/')
            ];
        }

        return $formattedData;
    }

    /**
     * @param string $pattern
     * @param int $limit
     *
     * @return array<string, array<string, string|int>>
     */
    protected function getByPattern(string $pattern, int $limit): array
    {
        if (!$pattern) {
            return [];
        }

        $batchSize = min($limit, 1000);

        $data = [];

        /** @var list<string> $keys */
        foreach ($this->storageClient->scan($pattern, $batchSize) as $keys) {
            $data = array_merge($data, $this->getByKeys($keys));
            if (count($data) >= $limit) {
                break;
            }
        }

        return $data;
    }

    /**
     * @param array<string, array<string, string|int>> $data
     *
     * @return array<string, array<string, string|int>>
     */
    protected function extendDataWithInfo(array $data): array
    {
        $extendedData = [];
        foreach ($data as $key => $keyValue) {
            $extendedData[$key] = [
                ...$keyValue,
                'type' => (string)$this->storageClient->getClient()->type($key),
                'ttl' => $this->formatTtl($this->storageClient->ttl($key)),
            ];
        }

        return $extendedData;
    }

    /**
     * @param int $ttl
     *
     * @return string
     */
    protected function formatTtl(int $ttl): string
    {
        if ($ttl < 0) {
            return '/';
        }

        if ($ttl < 60) {
            return $ttl . ' s';
        }

        if ($ttl < 3600) {
            return round($ttl / 60, 1) . ' m';
        }

        if ($ttl < 86400) {
            return round($ttl / 3600, 1) . ' h';
        }

        return round($ttl / 86400, 1) . ' d';
    }
}
