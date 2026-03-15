# Storage Bundle

[![CI](https://github.com/philipphermes/storage-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/philipphermes/storage-bundle/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-%3E%3D%207.4-8892BF.svg)](https://symfony.com)

A Symfony bundle that wraps Predis and exposes a StorageClient with essential storage operations,
while still allowing direct access to the underlying client for advanced use cases.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
    - [Configuration Examples](#configuration-examples)
- [Usage](#usage)
    - [Basic Operations](#basic-operations)
    - [Batch Operations](#batch-operations)
    - [Delete Operations](#delete-operations)
    - [Key Management](#key-management)
    - [Counters](#counters)
    - [Scanning Keys](#scanning-keys)
    - [Direct Client Access](#direct-client-access)
- [Console Commands](#console-commands)
    - [Get Key Value](#read-storage-data)
    - [Clean Storage](#clean-storage)
- [Development](#development)

## Requirements

- PHP 8.3 or higher
- Symfony 7.4+ / 8.0+
- Redis or Valkey server

## Installation

Install the bundle via Composer:

```bash
composer require philipphermes/storage-bundle
```

The bundle will be automatically registered in your Symfony application.

## Configuration

Configure the Redis/Valkey connection in `config/packages/storage.yaml`:

```yaml
storage:
  schema: tcp         # Connection schema (tcp, unix)
  host: localhost     # Redis host
  port: 6379          # Redis port
  path: ~             # Unix socket path (alternative to host/port)
  username: ~         # Redis username (optional)
  password: ~         # Redis password (optional)
  persistent: false   # Use persistent connection
```

### Configuration Examples

**Standard TCP connection:**

```yaml
storage:
  schema: tcp
  host: localhost
  port: 6379
```

**With authentication:**

```yaml
storage:
  schema: tcp
  host: redis.example.com
  port: 6379
  username: myuser
  password: '%env(REDIS_PASSWORD)%'
```

**Unix socket:**

```yaml
storage:
  schema: unix
  path: /var/run/redis/redis.sock
```

## Usage

Inject the `StorageClientInterface` into your services:

```php
use PhilippHermes\StorageBundle\Client\StorageClientInterface;

class MyService
{
    public function __construct(
        private StorageClientInterface $storage
    ) {}
}
```

### Basic Operations

#### Set and Get

```php
// Store a string
$this->storage->set('user:name', 'John Doe');

// Store an array (automatically JSON encoded)
$this->storage->set('user:1', ['name' => 'John', 'email' => 'john@example.com']);

// Retrieve a value
$name = $this->storage->get('user:name'); // "John Doe"
$user = $this->storage->get('user:1');    // ['name' => 'John', 'email' => '...']

// Returns null if key doesn't exist
$missing = $this->storage->get('nonexistent'); // null
```

#### Set with Expiration

```php
use PhilippHermes\StorageBundle\Client\StorageClient;

// Expire in 60 seconds
$this->storage->set(
    'session:token',
    'abc123',
    StorageClient::EXPIRE_RESOLUTION_EX,
    60
);

// Expire in 5000 milliseconds
$this->storage->set(
    'rate:limit',
    '100',
    StorageClient::EXPIRE_RESOLUTION_PX,
    5000
);
```

### Batch Operations

```php
// Set multiple keys at once
$this->storage->setMultiple([
    'user:1' => ['name' => 'John'],
    'user:2' => ['name' => 'Jane'],
    'user:3' => ['name' => 'Bob'],
]);

// Get multiple keys
$users = $this->storage->getMultiple(['user:1', 'user:2', 'user:3']);
// Returns: ['user:1' => [...], 'user:2' => [...], 'user:3' => [...]]
```

### Delete Operations

```php
// Delete a single key
$this->storage->delete('user:1');

// Delete multiple keys
$this->storage->deleteMultiple(['user:1', 'user:2', 'user:3']);
```

### Key Management

```php
// Check if key exists
if ($this->storage->exists('user:1')) {
    // Key exists
}

// Get TTL in seconds (-1 = no expiry, -2 = doesn't exist)
$ttl = $this->storage->ttl('session:token');

// Set expiration on existing key
$this->storage->expire('user:1', 3600); // Expire in 1 hour

// Remove expiration from key
$this->storage->persist('user:1');
```

### Counters

```php
// Increment a counter
$views = $this->storage->increment('page:views');        // +1
$views = $this->storage->increment('page:views', 5);     // +5

// Decrement a counter
$stock = $this->storage->decrement('product:stock');     // -1
$stock = $this->storage->decrement('product:stock', 10); // -10
```

### Scanning Keys

```php
// Iterate through keys matching a pattern
foreach ($this->storage->scan('user:*', 100) as $keyBatch) {
    foreach ($keyBatch as $key) {
        echo "Found key: $key\n";
    }
}
```

### Direct Client Access

For advanced operations not covered by the wrapper:

```php
$client = $this->storage->getClient();
$client->lpush('queue:jobs', 'job1');
$client->rpop('queue:jobs');
```

## Console Commands

The bundle provides several console commands for managing and inspecting your Redis/Valkey storage.

### Read Storage Data

Retrieve and display a key's value with info:

```bash
# Get a key values by keys
php bin/console storage:read --keys="user:1,user:2,..."

# Get a key values by pattern
php bin/console storage:read --pattern="user:*"

# Show info (TTL, type)
php bin/console storage:read --pattern="user:*" --info

# Limit
php bin/console storage:read --pattern="user:*" --limit=50 #Default 100
```

### Clean Storage

Delete all keys matching a pattern:

```bash
# Delete all keys (with confirmation)
php bin/console storage:clean

# Delete keys matching a pattern
php bin/console storage:clean --pattern="cache:*"

# Skip confirmation prompt
php bin/console storage:clean --force

# Custom batch size
php bin/console storage:clean --pattern="session:*" --batch=500
```

---

## Development

### Static Analysis

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

### Testing

```bash
docker compose up -d
```

```bash
vendor/bin/phpunit
```

With coverage report:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage-report
```