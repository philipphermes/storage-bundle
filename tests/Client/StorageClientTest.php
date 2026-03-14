<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Tests\Client;

use PhilippHermes\StorageBundle\Client\StorageClient;
use PhilippHermes\StorageBundle\Client\StorageClientInterface;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class StorageClientTest extends TestCase
{
    protected StorageClientInterface $storageClient;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $parameterBag = new ParameterBag([
            'storage.schema' => 'tcp',
            'storage.host' => 'localhost',
            'storage.port' => 6379,
            'storage.path' => null,
            'storage.username' => null,
            'storage.password' => 'secret',
            'storage.persistent' => false
        ]);

        $this->storageClient = new StorageClient($parameterBag);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->storageClient->scan() as $keys) {
            $this->storageClient->deleteMultiple($keys);
        }
    }

    /**
     * @return void
     */
    public function testSetAndGet(): void
    {
        $this->storageClient->set('kv:foo', 'bar');

        self::assertSame('bar', $this->storageClient->get('kv:foo'));
    }

    /**
     * @return void
     */
    public function testSetAndGetArray(): void
    {
        $this->storageClient->set('kv:user:1', ['name' => 'test']);

        self::assertSame(['name' => 'test'], $this->storageClient->get('kv:user:1'));
    }

    /**
     * @return void
     */
    public function testSetAndGetMultiple(): void
    {
        $this->storageClient->setMultiple([
            'kv:foo' => 'bar',
            'kv:bar' => 'foo',
        ]);

        $values = $this->storageClient->getMultiple([
            'kv:foo',
            'kv:bar'
        ]);

        self::assertSame('bar', $values['kv:foo']);
        self::assertSame('foo', $values['kv:bar']);
    }

    /**
     * @return void
     */
    public function testSetAndGetMultipleArray(): void
    {
        $this->storageClient->setMultiple([
            'kv:user:1' => ['name' => 'test 1'],
            'kv:user:2' => ['name' => 'test 2'],
        ]);

        $values = $this->storageClient->getMultiple([
            'kv:user:1',
            'kv:user:2'
        ]);

        self::assertSame(['name' => 'test 1'], $values['kv:user:1']);
        self::assertSame(['name' => 'test 2'], $values['kv:user:2']);
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $this->storageClient->set('kv:foo', 'bar');
        $this->storageClient->delete('kv:foo');

        self::assertFalse($this->storageClient->exists('kv:foo'));
    }

    /**
     * @return void
     */
    public function testDeleteMultiple(): void
    {
        $this->storageClient->setMultiple([
            'kv:foo' => 'bar',
            'kv:bar' => 'foo',
        ]);
        $this->storageClient->deleteMultiple([
            'kv:foo',
            'kv:bar',
        ]);

        self::assertFalse($this->storageClient->exists('kv:foo'));
        self::assertFalse($this->storageClient->exists('kv:bar'));
    }

    /**
     * @return void
     */
    public function testScan(): void
    {
        $data = [];
        for ($i = 1; $i <= 100; $i++) {
            $data['kv:user:' . $i] = ['name' => 'test ' . $i];
        }

        $this->storageClient->setMultiple([
            ...$data,
            'kv:foo' => 'bar',
        ]);

        $keys = [];
        foreach ($this->storageClient->scan('kv:user:*', 10) as $scannedKeys) {
            $keys = array_merge($keys, $scannedKeys);
        }

        self::assertCount(100, $keys);
    }

    /**
     * @return void
     */
    public function testTTL(): void
    {
        $this->storageClient->set('kv:foo', 'bar', StorageClient::EXPIRE_RESOLUTION_EX, 5);

        $ttl = $this->storageClient->ttl('kv:foo');

        $this->storageClient->expire('kv:foo', 0);

        self::assertGreaterThanOrEqual(1, $ttl);
        self::assertLessThanOrEqual(5, $ttl);

        self::assertFalse($this->storageClient->exists('kv:foo'));
    }

    /**
     * @return void
     */
    public function testTTLAndRemoveExpiration(): void
    {
        $this->storageClient->set('kv:foo', 'bar', StorageClient::EXPIRE_RESOLUTION_EX, 5);

        $ttlBefore = $this->storageClient->ttl('kv:foo');
        $this->storageClient->persist('kv:foo');
        $ttlAfter = $this->storageClient->ttl('kv:foo');

        self::assertGreaterThanOrEqual(1, $ttlBefore);
        self::assertLessThanOrEqual(5, $ttlBefore);

        self::assertSame(-1, $ttlAfter);
        self::assertTrue($this->storageClient->exists('kv:foo'));
    }

    /**
     * @return void
     */
    public function testIncrementAndDecrement(): void
    {
        $this->storageClient->set('kv:foo', 1);

        $incremented = $this->storageClient->increment('kv:foo', 10);
        $decremented = $this->storageClient->decrement('kv:foo', 1);

        $value = $this->storageClient->get('kv:foo');

        self::assertSame(11, $incremented);
        self::assertSame(10, $decremented);
        self::assertSame(10, $value);
    }

    /**
     * @return void
     */
    public function testGetClient(): void
    {
        self::assertInstanceOf(ClientInterface::class, $this->storageClient->getClient());
    }
}