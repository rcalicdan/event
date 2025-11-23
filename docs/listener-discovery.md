# Listener Discovery

Automatic listener registration using PHP attributes—the recommended approach for building your event bus.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Attribute Types](#attribute-types)
  - [ListenTo](#listento)
  - [ListenOnce](#listenonce)
- [Annotation Targets](#annotation-targets)
  - [Class-Level Attributes](#class-level-attributes)
  - [Method-Level Attributes](#method-level-attributes)
  - [Function-Level Attributes](#function-level-attributes)
  - [Multiple Attributes](#multiple-attributes)
- [Discovery Configuration](#discovery-configuration)
  - [Basic Discovery](#basic-discovery)
  - [Multiple Directories](#multiple-directories)
  - [Caching](#caching)
  - [Cache Refresh](#cache-refresh)
  - [Error Handling](#error-handling)
  - [Dependency Injection](#dependency-injection)
  - [Custom Emitter](#custom-emitter)
- [Best Practices](#best-practices)
- [Performance Optimization](#performance-optimization)

## Overview

Listener Discovery eliminates the need for manual event listener registration. Instead of writing boilerplate code to wire up your event bus, you simply annotate your classes, methods, and functions with `#[ListenTo]` or `#[ListenOnce]` attributes. The library automatically discovers, registers, and caches all listeners during application bootstrap.

**Benefits**:
- **Zero boilerplate**: No manual registration code
- **Declarative**: Intent is clear from the attribute itself
- **Centralized**: All listener logic lives with the listener code
- **Scalable**: Add hundreds of listeners without cluttering bootstrap files
- **Fast**: Production caching ensures zero performance overhead
- **Type-safe**: Works seamlessly with backed enums

## Quick Start

```php
use Rcalicdan\Event\Attributes\ListenTo;
use Rcalicdan\Event\ListenerDiscovery;
use Rcalicdan\Event\Event;

// Step 1: Annotate your listener
#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        // Send welcome email
    }
}

// Step 2: Run discovery during bootstrap (do this once)
ListenerDiscovery::discover(__DIR__ . '/src/Listeners');

// Step 3: Emit events anywhere in your application
Event::emit('user.registered', new UserDTO(
    userId: $user->id,
    email: $user->email,
    registeredAt: new DateTimeImmutable()
));
```

That's it! No manual registration, no service providers, no configuration files.

## Attribute Types

### ListenTo

The `#[ListenTo]` attribute registers a listener that will be called every time the event is emitted.

```php
use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        // Called every time 'user.registered' is emitted
    }
}
```

**Constructor Parameters**:

```php
#[ListenTo(
    event: 'user.registered',  // string or BackedEnum (required)
    method: 'handle',           // Method name to call (default: 'handle')
    priority: 0                 // Execution priority (default: 0, higher = earlier)
)]
```

**Examples**:

```php
// With priority
#[ListenTo('order.placed', priority: 10)]
class ValidateOrder { }

// With custom method
#[ListenTo('order.placed', method: 'processOrder')]
class OrderProcessor
{
    public function processOrder(OrderDTO $order) { }
}

// With backed enums
enum UserEvents: string {
    case Registered = 'user.registered';
}

#[ListenTo(UserEvents::Registered)]
class SendWelcomeEmail { }
```

### ListenOnce

The `#[ListenOnce]` attribute registers a listener that will be called only the first time the event is emitted, then automatically removed.

```php
use Rcalicdan\Event\Attributes\ListenOnce;

#[ListenOnce('app.initialized')]
class SetupDatabase
{
    public function handle()
    {
        // Called only once, then removed
        $this->runMigrations();
    }
}
```

**Use Cases**:
- Application initialization
- One-time setup tasks
- Resource allocation that shouldn't repeat
- Migration or upgrade logic

**Constructor Parameters** (same as `ListenTo`):
```php
#[ListenOnce(
    event: 'app.initialized',
    method: 'handle',
    priority: 0
)]
```

## Annotation Targets

### Class-Level Attributes

Annotate a class to listen to one or more events. The specified method will be called when the event is emitted.

```php
#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function __construct(
        private MailerService $mailer
    ) {}
    
    public function handle(UserDTO $user)
    {
        $this->mailer->send($user->email, 'Welcome!');
    }
}
```

**Default Method**: If not specified, the `handle` method is called by default.

**Custom Method**:
```php
#[ListenTo('user.registered', method: 'sendEmail')]
class UserNotifier
{
    public function sendEmail(UserDTO $user) { }
}
```

### Method-Level Attributes

Annotate individual methods to create multiple listeners in a single class.

```php
class OrderEventSubscriber
{
    #[ListenTo('order.placed')]
    public function onOrderPlaced(OrderDTO $order) { }
    
    #[ListenTo('order.shipped')]
    public function onOrderShipped(OrderDTO $order) { }
    
    #[ListenTo('order.cancelled')]
    public function onOrderCancelled(OrderDTO $order) { }
}
```

**Benefits**:
- Group related listeners in one class
- Share dependencies via constructor injection
- Easier to test related event handlers together

### Function-Level Attributes

Annotate standalone functions for simple listeners that don't require class state.

```php
#[ListenTo('user.login')]
function logUserActivity(UserDTO $user): void
{
    error_log("User {$user->userId} logged in");
}

#[ListenOnce('app.booted')]
function warmupCache(): void
{
    Cache::warmup();
}
```

**Use Cases**:
- Simple logging
- Quick prototyping
- Stateless operations
- Legacy code integration

### Multiple Attributes

Classes and methods can have multiple attributes to listen to different events.

```php
// Multiple events, same handler
#[ListenTo('order.placed')]
#[ListenTo('order.updated')]
#[ListenTo('order.shipped')]
class OrderLogger
{
    public function handle(OrderDTO $order)
    {
        $this->logOrderEvent($order);
    }
}

// Multiple events, different handlers
#[ListenTo('user.registered', method: 'onRegistered')]
#[ListenTo('user.deleted', method: 'onDeleted')]
class UserAuditLog
{
    public function onRegistered(UserDTO $user) { }
    public function onDeleted(UserDTO $user) { }
}

// Mixed class and method attributes
#[ListenTo('payment.received', method: 'processPayment')]
class PaymentProcessor
{
    public function processPayment(PaymentDTO $payment) { }
    
    #[ListenTo('payment.failed')]
    public function handleFailure(PaymentDTO $payment) { }
}
```

## Discovery Configuration

### Basic Discovery

The simplest form: scan a single directory.

```php
use Rcalicdan\Event\ListenerDiscovery;

ListenerDiscovery::discover(__DIR__ . '/src/Listeners');
```

This scans the directory recursively, finds all PHP files, parses attributes, and registers listeners.

### Multiple Directories

Scan multiple directories to organize listeners by feature or domain.

```php
ListenerDiscovery::discover([
    __DIR__ . '/src/Listeners',
    __DIR__ . '/src/Subscribers',
    __DIR__ . '/src/EventHandlers',
]);
```

**Organization Examples**:

```php
// By feature
ListenerDiscovery::discover([
    __DIR__ . '/src/User/Listeners',
    __DIR__ . '/src/Order/Listeners',
    __DIR__ . '/src/Payment/Listeners',
]);

// By layer
ListenerDiscovery::discover([
    __DIR__ . '/src/Application/Listeners',
    __DIR__ . '/src/Domain/EventSubscribers',
    __DIR__ . '/src/Infrastructure/Handlers',
]);
```

### Caching

Enable caching for production to avoid parsing attributes on every request.

```php
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache'
);
```

**How Caching Works**:
1. First run: Scans all files, parses attributes, registers listeners, writes cache
2. Subsequent runs: Loads listeners directly from cache (fast)
3. Cache file: `{cachePath}/{md5(directory)}-listeners.php`

**Disable Caching**:
```php
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: null  // No caching
);
```

### Cache Refresh

Automatically invalidate cache when listener files change (useful in development).

```php
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: true  // Check file modification times
);
```

**Recommended Configuration**:

```php
// Environment-based
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: $_ENV['APP_ENV'] === 'development'
);
```

### Error Handling

Control how listener errors are handled during execution.

```php
// Fail-fast mode (throw exceptions immediately)
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    failFast: true
);

// Resilient mode (catch exceptions, emit as 'error' events)
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    failFast: false
);

// Use environment configuration
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    failFast: null  // Reads from EVENT_THROW_ON_ERROR env var
);
```

**Modes**:

- **Fail-Fast** (`failFast: true`): Exceptions propagate immediately, execution stops. Use in development.
- **Resilient** (`failFast: false`): Exceptions are caught and emitted as `'error'` events. Use in production.

**Handling Error Events**:

When using resilient mode, create an error handler to catch exceptions from listeners:

```php
#[ListenTo('error')]
class ErrorHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {}
    
    public function handle(Throwable $error)
    {
        $this->logger->error('Event listener failed', [
            'exception' => get_class($error),
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);
    }
}
```

**Important**: Always create an error handler when using resilient mode, otherwise errors will be silently ignored.

### Dependency Injection

Integrate with PSR-11 containers for automatic dependency injection in listener classes.

```php
use Psr\Container\ContainerInterface;

$container = new YourContainer();

ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    container: $container
);
```

**How It Works**:

```php
// Listener with dependencies
#[ListenTo('order.placed')]
class NotifyWarehouse
{
    public function __construct(
        private WarehouseApiClient $api,
        private LoggerInterface $logger
    ) {}
    
    public function handle(OrderDTO $order)
    {
        // Dependencies automatically injected via container
        $this->api->sendOrder($order);
    }
}
```

**Fallback**: If container doesn't have the class, instantiates with `new ClassName()`.

### Custom Emitter

Inject a custom `EventEmitterInterface` implementation to replace the default emitter.

```php
use Rcalicdan\Event\EventEmitterInterface;

class AsyncEventEmitter implements EventEmitterInterface
{
    // Custom implementation
}

ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    emitter: new AsyncEventEmitter()
);
```

**Use Cases**:
- Queue-based async event processing
- Message broker integration (RabbitMQ, Kafka)
- Distributed event systems
- Testing with mock emitters

**Complete Configuration Example**:

```php
ListenerDiscovery::discover(
    directory: [
        __DIR__ . '/src/Listeners',
        __DIR__ . '/src/Subscribers',
    ],
    failFast: $_ENV['APP_ENV'] === 'development',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: $_ENV['APP_ENV'] === 'development',
    container: $container,
    emitter: new AsyncEventEmitter()
);
```

## Best Practices

### 1. Organize Listeners by Domain

```
src/
├── User/Listeners/
├── Order/Listeners/
└── Payment/Listeners/
```

### 2. Use Descriptive Class Names

```php
// Good: Clear intent
#[ListenTo('user.registered')]
class SendWelcomeEmail { }

// Bad: Vague purpose
#[ListenTo('user.registered')]
class UserListener { }
```

### 3. Use Enums for Type Safety

```php
enum UserEvents: string {
    case Registered = 'user.registered';
    case Updated = 'user.updated';
}

#[ListenTo(UserEvents::Registered)]
class SendWelcomeEmail { }
```

### 4. Group Related Listeners

```php
// Group related listeners in one class
class OrderEventSubscriber
{
    #[ListenTo('order.placed')]
    public function onPlaced(OrderDTO $order) { }
    
    #[ListenTo('order.shipped')]
    public function onShipped(OrderDTO $order) { }
}
```

### 5. Use Priority Wisely

```php
#[ListenTo('order.placed', priority: 100)]  // Critical: validation
class ValidateOrder { }

#[ListenTo('order.placed', priority: 50)]   // Core: business logic
class ProcessPayment { }

#[ListenTo('order.placed', priority: 0)]    // Side effects: logging
class LogOrder { }
```

### 6. Use Readonly DTOs

```php
readonly class OrderDTO
{
    public function __construct(
        public string $orderId,
        public float $total,
        public DateTimeImmutable $placedAt,
    ) {}
}

#[ListenTo('order.placed')]
class NotifyWarehouse
{
    public function handle(OrderDTO $order)
    {
        // Can't accidentally modify $order
    }
}
```

### 7. Keep Listeners Focused

```php
// Good: Single responsibility
#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        $this->mailer->send($user->email, 'Welcome!');
    }
}

// Bad: Too many responsibilities
#[ListenTo('user.registered')]
class UserRegistrationHandler
{
    public function handle(UserDTO $user)
    {
        $this->sendEmail($user);
        $this->createProfile($user);
        $this->assignRole($user);
        $this->logActivity($user);
        $this->notifyAdmins($user);
    }
}
```

### 8. Bootstrap Once

```php
// In application bootstrap (index.php, bootstrap.php)
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: $_ENV['APP_ENV'] === 'development',
    container: $container
);

// Don't call discover() multiple times or in request handlers
```

### 9. Always Handle Error Events

```php
// Always create an error handler when using resilient mode
#[ListenTo('error')]
class ErrorHandler
{
    public function handle(Throwable $error)
    {
        // Log, track, notify
    }
}
```

## Performance Optimization

### Production Configuration

```php
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    failFast: false,                // Resilient mode
    cachePath: __DIR__ . '/var/cache',
    refreshCache: false,            // Never check file changes
    container: $container
);
```

### Cache Warm-Up Script

```php
// bin/cache-warmup.php
<?php

require __DIR__ . '/../vendor/autoload.php';

// Clear old cache
array_map('unlink', glob(__DIR__ . '/../var/cache/*-listeners.php'));

// Rebuild cache
ListenerDiscovery::discover(
    directory: __DIR__ . '/../src/Listeners',
    cachePath: __DIR__ . '/../var/cache',
    refreshCache: false
);

echo "Cache warmed up!\n";
```

**Add to composer.json**:
```json
{
    "scripts": {
        "post-install-cmd": [
            "@php bin/cache-warmup.php"
        ]
    }
}
```

### Performance Benchmark

- **Without cache**: ~50-200ms (depending on number of listeners)
- **With cache**: ~1-5ms (just loading a PHP file)

---

[← Back to Main Documentation](../README.md) | [Next: Basic Usage →](basic-usage.md) | [Examples →](examples.md)