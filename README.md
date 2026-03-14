# Storage Bundle

[![CI](https://github.com/philipphermes/storage-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/philipphermes/storage-bundle/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-%3E%3D%207.4-8892BF.svg)](https://symfony.com)

A Symfony bundle providing a simple and elegant Redis/Valkey storage client with automatic JSON serialization, transaction support, and essential caching operations.

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
  - [Server Information](#server-information)
  - [List Keys](#list-keys)
  - [Get Key Value](#get-key-value)
  - [Clean Storage](#clean-storage)
- [Best Practices](#best-practices)
- [Examples](#examples)
  - [Caching Service](#caching-service)
  - [Rate Limiter](#rate-limiter)
  - [Session Storage](#session-storage)
- [Development](#development)

## Features

- 🚀 Simple, intuitive API for Redis/Valkey operations
- 🔄 Automatic JSON serialization/deserialization for arrays
- 💾 Batch operations with transaction support
- 🔍 SCAN-based iteration for safe key traversal
- ⚡ TTL and expiration management
- 🔢 Atomic increment/decrement operations
- 🧹 Bulk deletion with pattern matching
- 🛠️ Console commands for maintenance

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
    schema: tcp          # Connection schema (tcp, unix)
    host: localhost      # Redis host
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

// Delete all keys matching a pattern
$deletedCount = $this->storage->deleteAll('cache:*');
echo "Deleted $deletedCount keys";
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

### Server Information

Display comprehensive server statistics and information:

```bash
# Show server info
php bin/console storage:info
```

**Output includes:**
- Server version, OS, and uptime
- Memory usage and fragmentation
- Connection statistics
- Keyspace information (number of keys per database)
- Hit/miss ratio
- Persistence status

### List Keys

List and search for keys with various filtering options:

```bash
# List all keys (limited to 100 by default)
php bin/console storage:keys

# List keys matching a pattern
php bin/console storage:keys --pattern="user:*"

# Increase limit
php bin/console storage:keys --pattern="cache:*" --limit=500

# Show TTL for each key
php bin/console storage:keys --pattern="session:*" --show-ttl

# Show type for each key
php bin/console storage:keys --pattern="*" --show-type

# Combine options
php bin/console storage:keys -p "user:*" -l 50 -t --show-type

# Short options
php bin/console storage:keys -p "cache:*" -l 200 -t
```

### Get Key Value

Retrieve and display a key's value with metadata:

```bash
# Get a key value
php bin/console storage:get user:123

# Show metadata (TTL, type, size)
php bin/console storage:get user:123 --show-meta

# Output as JSON
php bin/console storage:get user:123 --format=json

# Combine options
php bin/console storage:get cache:product:456 -m -f json

# Short options
php bin/console storage:get session:abc123 -m
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
php bin/console storage:clean --pattern="session:*" --batch-size=500

# Short options
php bin/console storage:clean -p "temp:*" -b 2000 -f
```

## Best Practices

### 1. Use Namespaced Keys

```php
// Good - organized and easy to manage
$this->storage->set('user:1:profile', $data);
$this->storage->set('cache:products:list', $data);
$this->storage->set('session:abc123', $data);

// Bad - hard to manage and clean up
$this->storage->set('user1', $data);
$this->storage->set('products', $data);
```

### 2. Always Set Expiration for Temporary Data

```php
// Cache with TTL
$this->storage->set(
    'cache:product:123',
    $product,
    StorageClient::EXPIRE_RESOLUTION_EX,
    3600
);

// Session with TTL
$this->storage->set(
    'session:' . $token,
    $sessionData,
    StorageClient::EXPIRE_RESOLUTION_EX,
    1800
);
```

### 3. Use Batch Operations for Multiple Keys

```php
// Good - single transaction
$this->storage->setMultiple([
    'user:1' => $user1,
    'user:2' => $user2,
    'user:3' => $user3,
]);

// Bad - multiple round trips
$this->storage->set('user:1', $user1);
$this->storage->set('user:2', $user2);
$this->storage->set('user:3', $user3);
```

### 4. Use SCAN for Large Datasets

```php
// Good - memory efficient, won't block Redis
foreach ($this->storage->scan('cache:*', 1000) as $keyBatch) {
    $this->storage->deleteMultiple($keyBatch);
}

// Bad - could block Redis and use too much memory
// Don't use KEYS command for production
```

## Examples

### Caching Service

```php
class ProductCache
{
    public function __construct(
        private StorageClientInterface $storage
    ) {}

    public function get(int $productId): ?array
    {
        return $this->storage->get("cache:product:$productId");
    }

    public function set(int $productId, array $product, int $ttl = 3600): void
    {
        $this->storage->set(
            "cache:product:$productId",
            $product,
            StorageClient::EXPIRE_RESOLUTION_EX,
            $ttl
        );
    }

    public function invalidate(int $productId): void
    {
        $this->storage->delete("cache:product:$productId");
    }

    public function invalidateAll(): int
    {
        return $this->storage->deleteAll('cache:product:*');
    }
}
```

### Rate Limiter

```php
class RateLimiter
{
    public function __construct(
        private StorageClientInterface $storage
    ) {}

    public function isAllowed(string $identifier, int $limit, int $window): bool
    {
        $key = "rate:$identifier";
        $current = (int)$this->storage->get($key) ?? 0;

        if ($current >= $limit) {
            return false;
        }

        if ($current === 0) {
            $this->storage->set($key, 1, StorageClient::EXPIRE_RESOLUTION_EX, $window);
        } else {
            $this->storage->increment($key);
        }

        return true;
    }
}
```

### Session Storage

```php
class SessionManager
{
    public function __construct(
        private StorageClientInterface $storage
    ) {}

    public function create(array $data, int $ttl = 1800): string
    {
        $token = bin2hex(random_bytes(32));
        $this->storage->set(
            "session:$token",
            $data,
            StorageClient::EXPIRE_RESOLUTION_EX,
            $ttl
        );
        return $token;
    }

    public function get(string $token): ?array
    {
        return $this->storage->get("session:$token");
    }

    public function refresh(string $token, int $ttl = 1800): bool
    {
        return $this->storage->expire("session:$token", $ttl);
    }

    public function destroy(string $token): void
    {
        $this->storage->delete("session:$token");
    }
}
```

---

## Development

### Static Analysis

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

### Testing

Start Redis container
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