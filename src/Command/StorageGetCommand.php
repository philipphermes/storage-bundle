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

#[AsCommand(name: 'storage:get', description: 'Retrieve and display a key value')]
class StorageGetCommand extends Command
{
    public function __construct(
        private readonly StorageClientInterface $storageClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The key to retrieve')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (text, json)', 'text')
            ->addOption('show-meta', 'm', InputOption::VALUE_NONE, 'Show metadata (TTL, type, size)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $client = $this->storageClient->getClient();

        $key = $input->getArgument('key');
        $format = $input->getOption('format');
        $showMeta = $input->getOption('show-meta');

        if (!$this->storageClient->exists($key)) {
            $io->error(sprintf('Key "%s" does not exist.', $key));
            return Command::FAILURE;
        }

        try {
            $value = $this->storageClient->get($key);
            $type = $client->type($key);
            $ttl = $this->storageClient->ttl($key);
        } catch (\Exception $e) {
            $io->error('Failed to retrieve key: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($showMeta) {
            $io->section('Metadata');
            $metaData = [
                ['Key', $key],
                ['Type', $type],
                ['TTL', $this->formatTtl($ttl)],
            ];

            if (is_string($value)) {
                $metaData[] = ['Size', strlen($value) . ' bytes'];
            } elseif (is_array($value)) {
                $metaData[] = ['Elements', count($value)];
            }

            $io->table(['Property', 'Value'], $metaData);
        }

        $io->section('Value');

        switch ($format) {
            case 'json':
                if (is_array($value)) {
                    $output->writeln(json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                } else {
                    $output->writeln(json_encode(['value' => $value], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                }
                break;

            case 'text':
            default:
                if (is_array($value)) {
                    $this->displayArray($io, $value);
                } else {
                    $io->text((string)($value ?? ''));
                }
                break;
        }

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
            return $ttl . ' seconds';
        }
        if ($ttl < 3600) {
            return round($ttl / 60, 1) . ' minutes';
        }
        if ($ttl < 86400) {
            return round($ttl / 3600, 1) . ' hours';
        }
        return round($ttl / 86400, 1) . ' days';
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private function displayArray(SymfonyStyle $io, array $array, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $io->text($indent . $key . ':');
                $this->displayArray($io, $value, $depth + 1);
            } else {
                $io->text($indent . $key . ': ' . $value);
            }
        }
    }
}
