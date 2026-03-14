<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Command;

use PhilippHermes\StorageBundle\Client\StorageClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'storage:info', description: 'Display Redis/Valkey server information and statistics')]
class StorageInfoCommand extends Command
{
    public function __construct(
        private readonly StorageClientInterface $storageClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $client = $this->storageClient->getClient();

        try {
            $info = $client->info();
        } catch (\Exception $e) {
            $io->error('Failed to retrieve server information: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Storage Server Information');

        // Server section
        if (isset($info['Server'])) {
            $io->section('Server');
            $serverData = [
                ['Redis Version', $info['Server']['redis_version'] ?? 'N/A'],
                ['OS', $info['Server']['os'] ?? 'N/A'],
                ['Process ID', $info['Server']['process_id'] ?? 'N/A'],
                ['Uptime (days)', $info['Server']['uptime_in_days'] ?? 'N/A'],
            ];
            $io->table(['Property', 'Value'], $serverData);
        }

        // Memory section
        if (isset($info['Memory'])) {
            $io->section('Memory');
            $memoryData = [
                ['Used Memory', $this->formatBytes($info['Memory']['used_memory'] ?? 0)],
                ['Used Memory Peak', $this->formatBytes($info['Memory']['used_memory_peak'] ?? 0)],
                ['Total System Memory', $this->formatBytes($info['Memory']['total_system_memory'] ?? 0)],
                ['Memory Fragmentation Ratio', $info['Memory']['mem_fragmentation_ratio'] ?? 'N/A'],
            ];
            $io->table(['Property', 'Value'], $memoryData);
        }

        // Stats section
        if (isset($info['Stats'])) {
            $io->section('Statistics');
            $statsData = [
                ['Total Connections', number_format($info['Stats']['total_connections_received'] ?? 0)],
                ['Total Commands', number_format($info['Stats']['total_commands_processed'] ?? 0)],
                ['Keyspace Hits', number_format($info['Stats']['keyspace_hits'] ?? 0)],
                ['Keyspace Misses', number_format($info['Stats']['keyspace_misses'] ?? 0)],
            ];

            $hits = $info['Stats']['keyspace_hits'] ?? 0;
            $misses = $info['Stats']['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
            $statsData[] = ['Hit Rate', $hitRate . '%'];

            $io->table(['Property', 'Value'], $statsData);
        }

        // Keyspace section
        if (isset($info['Keyspace'])) {
            $io->section('Keyspace');
            $keyspaceData = [];
            foreach ($info['Keyspace'] as $db => $data) {
                if (is_array($data)) {
                    $keys = $data['keys'] ?? 0;
                    $expires = $data['expires'] ?? 0;
                    $keyspaceData[] = [$db, number_format($keys), number_format($expires)];
                }
            }
            if (!empty($keyspaceData)) {
                $io->table(['Database', 'Keys', 'Expires'], $keyspaceData);
            } else {
                $io->text('No keys in database');
            }
        }

        // Clients section
        if (isset($info['Clients'])) {
            $io->section('Clients');
            $clientsData = [
                ['Connected Clients', $info['Clients']['connected_clients'] ?? 'N/A'],
                ['Blocked Clients', $info['Clients']['blocked_clients'] ?? 'N/A'],
            ];
            $io->table(['Property', 'Value'], $clientsData);
        }

        // Persistence section
        if (isset($info['Persistence'])) {
            $io->section('Persistence');
            $persistenceData = [
                ['Loading', ($info['Persistence']['loading'] ?? 0) ? 'Yes' : 'No'],
                ['RDB Changes Since Last Save', $info['Persistence']['rdb_changes_since_last_save'] ?? 'N/A'],
                ['RDB Last Save Time', isset($info['Persistence']['rdb_last_save_time']) ? date('Y-m-d H:i:s', $info['Persistence']['rdb_last_save_time']) : 'N/A'],
            ];
            $io->table(['Property', 'Value'], $persistenceData);
        }

        $io->success('Server information retrieved successfully');

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = (int)min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
