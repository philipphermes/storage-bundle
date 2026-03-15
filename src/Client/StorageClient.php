<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Client;

use Predis\Client;
use Predis\ClientInterface;
use Predis\Transaction\MultiExec;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class StorageClient implements StorageClientInterface
{
    protected static ?ClientInterface $client = null;

    /**
     * @param StorageConfig $storageConfig
     * @param SerializerInterface $serializer
     */
    public function __construct(
        protected StorageConfig $storageConfig,
        protected SerializerInterface $serializer,
    )
    {
    }

    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface
     */
    public function set(string $key, string|int|array|object $value, ?string $expireResolution = null, ?int $expireTtl = null): void
    {
        if (is_array($value) || is_object($value)) {
            $value = $this->serializer->serialize($value, 'json');
        }

        $this->getClient()->set($key, $value, $expireResolution, $expireTtl);
    }

    /**
     * @inheritDoc
     *
     * @throws ExceptionInterface
     */
    public function setMultiple(array $keysValues, ?string $expireResolution = null, ?int $expireTtl = null): void
    {
        foreach ($keysValues as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $keysValues[$key] = $this->serializer->serialize($value, 'json');
            }
        }

        /** @var Client $client */
        $client = $this->getClient();

        $client->transaction(function (MultiExec $tx) use ($keysValues, $expireResolution, $expireTtl) {
            foreach ($keysValues as $key => $value) {
                $tx->set($key, $value, $expireResolution, $expireTtl);
            }
        });
    }

    /**
     * @inheritDoc
     *
     * @throws \JsonException
     */
    public function get(string $key): string|int|array|null
    {
        $value = $this->getClient()->get($key);
        if (!$value) {
            return null;
        }

        if (json_validate($value)) {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /**
     * @inheritDoc
     *
     * @throws \JsonException
     */
    public function getMultiple(array $keys): array
    {
        $keys = array_unique($keys);

        $values = $this->getClient()->mget($keys);
        $result = array_combine($keys, $values);

        foreach ($result as $key => $value) {
            if ($value && json_validate($value)) {
                $result[$key] = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        $this->getClient()->del($key);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(array $keys): void
    {
        $keys = array_unique($keys);

        $this->getClient()->del($keys);
    }

    /**
     * @inheritDoc
     */
    public function scan(string $pattern = '*', int $batchSize = 1000): \Generator
    {
        $client = $this->getClient();
        $cursor = 0;

        do {
            $result = $client->scan($cursor, ['MATCH' => $pattern, 'COUNT' => $batchSize]);
            $cursor = (int)$result[0];
            $keys = $result[1];

            if (!empty($keys)) {
                yield $keys;
            }
        } while ($cursor !== 0);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key): bool
    {
        return (bool)$this->getClient()->exists($key);
    }

    /**
     * @inheritDoc
     */
    public function ttl(string $key): int
    {
        return $this->getClient()->ttl($key);
    }

    /**
     * @inheritDoc
     */
    public function expire(string $key, int $seconds): bool
    {
        return (bool)$this->getClient()->expire($key, $seconds);
    }

    /**
     * @inheritDoc
     */
    public function persist(string $key): bool
    {
        return (bool)$this->getClient()->persist($key);
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $value = 1): int
    {
        return $this->getClient()->incrby($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->getClient()->decrby($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function getClient(): ClientInterface
    {
        if (!self::$client instanceof ClientInterface) {
            self::$client = new Client(
                $this->storageConfig->getParameters(), //TODO maybe add validation
                $this->storageConfig->getOptions(),
            );
        }

        return self::$client;
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        $this->getClient()->connect();
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->getClient()->disconnect();
    }
}