<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Client;

use Predis\Client;
use Predis\ClientInterface;
use Predis\Transaction\MultiExec;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StorageClient implements StorageClientInterface
{
    /**
     * expiration in seconds
     */
    public const string EXPIRE_RESOLUTION_EX = 'EX';

    /**
     * expiration in milliseconds
     */
    public const string EXPIRE_RESOLUTION_PX = 'PX';

    protected static ?ClientInterface $client = null;

    /**
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(
        protected ParameterBagInterface $parameterBag,
    )
    {
    }

    /**
     * @inheritDoc
     *
     * @throws \JsonException
     */
    public function set(string $key, string|int|array $value, ?string $expireResolution = null, ?int $expireTtl = null): void
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        $this->getClient()->set($key, $value, $expireResolution, $expireTtl);
    }

    /**
     * @inheritDoc
     *
     * @throws \JsonException
     */
    public function setMultiple(array $keysValues, ?string $expireResolution = null, ?int $expireTtl = null): void
    {
        foreach ($keysValues as $key => $value) {
            if (is_array($value)) {
                $keysValues[$key] = json_encode($value, JSON_THROW_ON_ERROR);
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
                $this->getParameters(),
                [], //TODO for clusters and replication
            );
        }

        return self::$client;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getParameters(): array
    {
        $parameters = [];

        if ($this->parameterBag->has('storage.schema')) {
            $parameters['schema'] = $this->parameterBag->get('storage.schema');
        }

        if ($this->parameterBag->has('storage.host')) {
            $parameters['host'] = $this->parameterBag->get('storage.host');
        }

        if ($this->parameterBag->has('storage.port')) {
            $parameters['port'] = $this->parameterBag->get('storage.port');
        }

        if ($this->parameterBag->has('storage.path')) {
            $parameters['path'] = $this->parameterBag->get('storage.path');
        }

        if ($this->parameterBag->has('storage.username')) {
            $parameters['username'] = $this->parameterBag->get('storage.username');
        }

        if ($this->parameterBag->has('storage.password')) {
            $parameters['password'] = $this->parameterBag->get('storage.password');
        }

        if ($this->parameterBag->has('storage.persistent')) {
            $parameters['persistent'] = (bool)$this->parameterBag->get('storage.persistent');
        }

        # TODO ssl
        # 'ssl'    => ['cafile' => 'private.pem', 'verify_peer' => true],
        # https://www.php.net/manual/de/context.ssl.php

        return $parameters;
    }
}