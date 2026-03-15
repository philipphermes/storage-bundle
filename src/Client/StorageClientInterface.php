<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Client;

use Predis\ClientInterface;

interface StorageClientInterface
{
    public const string EXPIRE_RESOLUTION_SECONDS = 'EX';

    public const string EXPIRE_RESOLUTION_MILLISECONDS = 'PX';

    /**
     * @param string $key
     * @param string|int|array<array-key, mixed>|object $value
     * @param string|null $expireResolution
     * @param int|null $expireTtl
     *
     * @return void
     */
    public function set(string $key, string|int|array|object $value, ?string $expireResolution = null, ?int $expireTtl = null): void;

    /**
     * @param array<string, string|int|array<array-key, mixed>|object> $keysValues
     * @param string|null $expireResolution
     * @param int|null $expireTtl
     *
     * @return void
     */
    public function setMultiple(array $keysValues, ?string $expireResolution = null, ?int $expireTtl = null): void;

    /**
     * @param string $key
     *
     * @return string|int|array<array-key, mixed>|null
     */
    public function get(string $key): string|int|array|null;

    /**
     * @param list<string> $keys
     *
     * @return array<string, string|int|array<array-key, mixed>|null>
     */
    public function getMultiple(array $keys): array;

    /**
     * @param string $key
     *
     * @return void
     */
    public function delete(string $key): void;

    /**
     * @param list<string> $keys
     *
     * @return void
     */
    public function deleteMultiple(array $keys): void;

    /**
     * @param string $pattern
     * @param int $batchSize
     *
     * @return \Generator<array<array-key, string>>
     */
    public function scan(string $pattern = '*', int $batchSize = 1000): \Generator;

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key): bool;

    /**
     * @param string $key
     *
     * @return int
     */
    public function ttl(string $key): int;

    /**
     * @param string $key
     * @param int $seconds
     *
     * @return bool
     */
    public function expire(string $key, int $seconds): bool;

    /**
     * @param string $key
     *
     * @return bool
     */
    public function persist(string $key): bool;

    /**
     * @param string $key
     * @param int $value
     *
     * @return int
     */
    public function increment(string $key, int $value = 1): int;

    /**
     * @param string $key
     * @param int $value
     *
     * @return int
     */
    public function decrement(string $key, int $value = 1): int;

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface;

    /**
     * @return void
     */
    public function connect(): void;

    /**
     * @return void
     */
    public function disconnect(): void;
}