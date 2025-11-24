# Event Library Documentation

[![Tests](https://github.com/rcalicdan/event/actions/workflows/tests.yml/badge.svg)](https://github.com/rcalicdan/event/.github/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/rcalicdan/event/v/stable)](https://packagist.org/packages/rcalicdan/event)
[![License](https://poser.pugx.org/rcalicdan/event/license)](https://packagist.org/packages/rcalicdan/event)
[![PHP Version Require](https://poser.pugx.org/rcalicdan/event/require/php)](https://packagist.org/packages/rcalicdan/event)

A lightweight, high-performance event-driven message-bus for PHP that delivers true event emission through a simple, flexible, and powerful architecture.

## Table of Contents

- [Introduction](#introduction)
  - [Architecture Philosophy](#architecture-philosophy)
  - [Why This Library Exists](#why-this-library-exists)
  - [How This Library Works](#how-this-library-works)
  - [Best Practices for Event Data](#best-practices-for-event-data)
- [Setup & Installation](#setup--installation)
  - [Installation](#installation)
  - [Usage Patterns](#usage-patterns)
  - [Configuration](#configuration)
- [Documentation Sections](#documentation-sections)
- [Quick Example](#quick-example)
- [Features at a Glance](#features-at-a-glance)
- [Inspiration](#inspiration)
- [License](#license)
- [Contributing](#contributing)

## Introduction

This library provides a genuine event-driven system that treats events as immutable announcements of things that have already happened. Unlike many PHP event systems that disguise commands as events or tightly couple emitters with listeners through mutable objects and return values, this library embraces pure event-driven principles: fire-and-forget emission, zero coupling, and data immutability.

**The primary selling point**: Attribute-based listener discovery eliminates boilerplate event registration. Annotate your classes and methods with `#[ListenTo]` or `#[ListenOnce]`, run discovery once, and your entire event bus is automatically wired—no manual registration, no configuration files, just clean declarative code.

### Architecture Philosophy

The library is built on three core principles:

**Simplicity**: Clean, intuitive API that feels natural. Emit events, register listeners, and let the system handle the rest. No complex interfaces to implement, no mandatory base classes, no configuration files to manage.

**Flexibility**: Multiple usage patterns to fit your needs. Use traits for object-level events, standalone instances for module isolation, a global static facade for application-wide events, or **attribute-based discovery for automatic event bus registration**—the recommended approach for most applications.

**Power**: Advanced features when you need them. Event priorities for execution control, wildcard patterns for dynamic listening, one-time listeners for initialization logic, backed enums for type safety, intelligent caching for performance, and resilient error handling for production reliability.

### Why This Library Exists

Most PHP event systems follow patterns that fundamentally misunderstand event-driven architecture:

**Mutable Event Objects**: Many systems pass event objects by reference, allowing listeners to modify them. This isn't event emission—it's command passing. Events represent historical facts that should never change. This library passes immutable data to listeners, preserving event integrity.

**Bidirectional Coupling**: Systems that return modified events from dispatch methods create tight coupling between emitters and listeners. The emitter must handle returned state, listeners can break emitters by returning unexpected values, and testing becomes complex. This library uses fire-and-forget emission—emitters never receive responses from listeners.

**Stoppable Events as Anti-Pattern**: Many systems allow listeners to prevent other listeners from executing through explicit stop propagation methods or interfaces. This violates a core principle: if an event happened, all interested parties deserve notification. However, this library acknowledges a pragmatic reality—sometimes you need short-circuit behavior for performance optimization or logical flow control. Rather than providing explicit "stop" mechanisms that encourage misuse, this library uses a simple boolean convention: if a listener returns `false`, propagation stops. This is intentionally minimal and non-intrusive, meant for rare optimization cases, not as a primary feature to build logic around.

**Important**: Propagation control should be used sparingly and only for optimization. It is not a substitute for proper validation and authorization:

```php
// WRONG: Using events for validation
Event::on('user.register', function ($data) {
    if (!$validator->validate($data)) {
        return false; // Don't do this!
    }
});

// RIGHT: Validate before emitting
if (!$validator->validate($data)) {
    throw new ValidationException();
}
$user = User::create($data);
Event::emit('user.registered', $user); // Only emit what actually happened

// ACCEPTABLE: Performance optimization
Event::on('cache.check', function ($key) {
    if ($cache->has($key)) {
        return false; // Stop expensive cache rebuilding
    }
});
```

**Heavy Abstractions**: Many systems require implementing multiple interfaces, extending base classes, configuring service containers, and managing complex dependency graphs. This library works with plain PHP callables—closures, functions, methods—anything you can call.

**Manual Registration Boilerplate**: Most systems require tedious manual listener registration in bootstrap files or service providers. This library's **attribute-based discovery** eliminates this entirely—annotate your listeners and they're automatically discovered and registered.

**Framework Lock-In**: Event systems tightly integrated with frameworks force you to use framework patterns and limit portability. This library is framework-agnostic, working equally well in legacy applications, microservices, CLI tools, or modern frameworks.

### How This Library Works

The architecture is straightforward:

1. **Annotate your listeners** with `#[ListenTo]` or `#[ListenOnce]` attributes (recommended)
2. **Run discovery once** during application bootstrap to auto-register all listeners
3. **Emit events** when something meaningful happens in your application
4. **The library** handles execution, priority ordering, error handling, and memory management

Events are identified by strings or backed enums. Listeners are plain callables. No ceremony, no complexity.

```php
// Annotate your listener (recommended approach)
#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        // Send welcome email
    }
}

// Discover all listeners once during bootstrap
ListenerDiscovery::discover(__DIR__ . '/src/Listeners');

// Emit events anywhere in your application
Event::emit('user.registered', new UserDTO(
    userId: $user->id,
    email: $user->email,
    registeredAt: new DateTimeImmutable()
));
```

The library supports multiple usage patterns, all with the same powerful feature set:

- **Attribute-based discovery** **RECOMMENDED**: Annotate classes/methods for automatic registration
- **Trait-based**: Add `EventEmitterTrait` to any class for object-level events
- **Instance-based**: Create `EventEmitter` instances for module isolation
- **Static facade**: Use the `Event` class for application-wide events

The **attribute-based discovery pattern** is the primary mechanism for building your event bus and is recommended for most applications.

### Best Practices for Event Data

To maintain true event-driven principles, always pass immutable or effectively immutable data to listeners:

**Recommended Approaches**:

1. **Readonly DTO Classes (Recommended)**: The best approach for event data
```php
readonly class UserDTO
{
    public function __construct(
        public string $userId,
        public string $email,
        public DateTimeImmutable $registeredAt,
    ) {}
}

Event::emit('user.registered', new UserDTO(
    userId: $user->id,
    email: $user->email,
    registeredAt: new DateTimeImmutable()
));
```

2. **Value Objects**: Immutable by design
```php
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency
    ) {}
}
```

3. **Arrays of Scalar Values**: Simple and effective
```php
Event::emit('order.placed', [
    'orderId' => $order->id,
    'total' => $order->total,
    'items' => $order->items->count(),
]);
```

4. **Domain Entities**: When you must pass entities, understand listeners should treat them as read-only
```php
// Emit the entity
Event::emit('order.shipped', $order);

// Listeners should only read, never modify
Event::on('order.shipped', function (Order $order) {
    // GOOD: Reading state
    $tracking = $order->trackingNumber;
    
    // BAD: Modifying state
    // $order->markAsDelivered(); // Don't do this!
});
```

The readonly DTO approach is strongly recommended as it enforces immutability at the language level, making it impossible for listeners to corrupt event data.

## Setup & Installation

### Installation

Install via Composer:

```bash
composer require rcalicdan/event
```

**Requirements**: PHP 8.2 or higher

### Usage Patterns

The library offers multiple ways to work with events, each suited for different architectural needs:

#### 1. Attribute-Based Listener Discovery (RECOMMENDED)

**This is the primary and recommended way to build your event bus.** Annotate your listener classes, methods, and functions with attributes, then run discovery once during bootstrap. The library automatically scans, registers, and caches all listeners—no manual wiring required.

```php
use Rcalicdan\Event\Attributes\ListenTo;
use Rcalicdan\Event\Attributes\ListenOnce;
use Rcalicdan\Event\ListenerDiscovery;

// Class-level attribute
#[ListenTo('user.registered', priority: 10)]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        // Send email logic
    }
}

// Multiple events on one class
#[ListenTo('order.placed', method: 'onOrderPlaced')]
#[ListenTo('order.cancelled', method: 'onOrderCancelled')]
class OrderNotificationService
{
    public function onOrderPlaced(OrderDTO $order)
    {
        // Handle order placed
    }
    
    public function onOrderCancelled(OrderDTO $order)
    {
        // Handle order cancelled
    }
}

// Method-level attributes
class PaymentProcessor
{
    #[ListenTo('payment.received', priority: 5)]
    public function processPayment(PaymentDTO $payment)
    {
        // Process payment
    }
    
    #[ListenOnce('app.initialized')]
    public function setupPaymentGateway()
    {
        // One-time initialization
    }
}

// Function-level attributes
#[ListenTo('user.login')]
function logUserActivity(UserDTO $user): void
{
    // Log activity
}

// Discover and register all listeners once during bootstrap
ListenerDiscovery::discover(
    directory: [
        __DIR__ . '/src/Listeners',
        __DIR__ . '/src/Subscribers',
        __DIR__ . '/src/EventHandlers',
    ],
    failFast: false,                    // Resilient mode for production
    cachePath: __DIR__ . '/var/cache',  // Enable caching
    refreshCache: true,                 // Auto-refresh in development
    container: $container,              // PSR-11 container for DI
    emitter: null                       // Use default emitter
);
```

**Key Features**:
- **Zero boilerplate**: No manual registration code needed
- **Multiple attributes**: One class can listen to many events
- **Class, method, and function support**: Flexible annotation targets
- **Production caching**: Parse attributes once, load instantly from cache
- **Cache refresh**: Automatically detects file changes in development
- **PSR-11 integration**: Automatic dependency injection for listener classes
- **Custom emitter support**: Inject your own EventEmitterInterface implementation
- **Priority control**: Fine-tune execution order
- **One-time listeners**: `#[ListenOnce]` for setup/teardown logic

**When to use**: This is the recommended approach for most applications. Use it as your primary event bus mechanism.

[Learn more in Listener Discovery →](docs/listener-discovery.md)

#### 2. Using the Static Event Facade

The global `Event` class provides application-wide event handling through a convenient static API. Good for quick prototyping or simple applications.

```php
use Rcalicdan\Event\Event;

// Register listeners manually
Event::on('user.login', function (UserDTO $user) {
    logActivity($user);
});

// Emit from anywhere
Event::emit('user.login', $user);
```

**When to use**: Quick prototyping, simple applications, or when you need to dynamically register listeners at runtime.

[Learn more in Basic Usage →](docs/basic-usage.md)

#### 3. Using the EventEmitterTrait

Add event capabilities to any class by using the trait. Perfect for objects that need their own isolated event system.

```php
use Rcalicdan\Event\EventEmitterTrait;

class OrderProcessor
{
    use EventEmitterTrait;
    
    public function process(Order $order)
    {
        // Business logic
        $order->markAsPaid();
        
        // Emit events
        $this->emit('order.processed', new OrderDTO(...));
        $this->emit('inventory.reserved', $order->items);
    }
}

// Use it
$processor = new OrderProcessor();
$processor->on('order.processed', function (OrderDTO $order) {
    echo "Order {$order->orderId} processed!";
});
```

**When to use**: Domain objects, services, or any class that should manage its own listeners independently.

#### 4. Using the EventEmitter Class

Create standalone emitter instances for module-level event isolation.

```php
use Rcalicdan\Event\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('data.received', function ($data) {
    processData($data);
});

$emitter->emit('data.received', $payload);
```

**When to use**: Modules, plugins, or subsystems that need event isolation from the rest of the application.

#### 5. Custom Event Emitter Implementation

Implement `EventEmitterInterface` to create custom emitter behavior while maintaining compatibility.

```php
use Rcalicdan\Event\EventEmitterInterface;
use Rcalicdan\Event\Event;

class AsyncEventEmitter implements EventEmitterInterface
{
    // Custom implementation with async processing,
    // message queue integration, distributed events, etc.
}

// Option 1: Set globally
Event::setInstance(new AsyncEventEmitter());

// Option 2: Use with listener discovery
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    emitter: new AsyncEventEmitter()
);
```

**When to use**: Advanced scenarios requiring message queues, async processing, distributed systems, or custom event routing logic.

### Configuration

The library works with zero configuration but offers optional environment-based settings:

```env
# Throw exceptions immediately (fail-fast mode)
EVENT_THROW_ON_ERROR=true
```

Programmatic configuration:

```php
use Rcalicdan\Event\Event;

// Development: fail fast on errors
Event::failFast();

// Production: resilient error handling
Event::resilient();

// Prevent memory leaks
Event::setMaxListeners(100);
```

## Documentation Sections

### Core Concepts
- [**Listener Discovery**](docs/listener-discovery.md) - Attribute-based automatic listener registration (RECOMMENDED)
- [**Basic Usage**](docs/basic-usage.md) - Event emission, manual listener registration, and core patterns
- [**Advanced Features**](docs/advanced-features.md) - Priorities, wildcards, one-time listeners, error handling

### Reference
- [**API Reference**](docs/api-reference.md) - Complete method documentation
- [**Best Practices**](docs/best-practices.md) - Patterns, anti-patterns, and architectural guidance
- [**Examples**](docs/examples.md) - Real-world usage examples

## Quick Example

```php
use Rcalicdan\Event\Event;
use Rcalicdan\Event\Attributes\ListenTo;
use Rcalicdan\Event\Attributes\ListenOnce;
use Rcalicdan\Event\ListenerDiscovery;

// Define immutable event DTOs
readonly class OrderDTO
{
    public function __construct(
        public string $orderId,
        public float $total,
        public DateTimeImmutable $placedAt,
    ) {}
}

// Attribute-based listeners (recommended)
#[ListenTo('order.placed', priority: 10)]
class NotifyWarehouse
{
    public function handle(OrderDTO $order)
    {
        // Notify warehouse system
    }
}

#[ListenTo('order.placed', priority: 5)]
#[ListenTo('order.shipped')]
class SendCustomerNotification
{
    public function handle(OrderDTO $order)
    {
        // Send email to customer
    }
}

#[ListenOnce('app.initialized')]
function setupEventTracking(): void
{
    // One-time initialization
}

// Wildcard listener
#[ListenTo('order.*')]
class OrderLogger
{
    public function handle(OrderDTO $order)
    {
        logEvent('order_event', $order);
    }
}

// Type-safe events with enums
enum OrderEvents: string {
    case Placed = 'order.placed';
    case Shipped = 'order.shipped';
    case Delivered = 'order.delivered';
}

#[ListenTo(OrderEvents::Placed->value)]
class ProcessPayment
{
    public function handle(OrderDTO $order)
    {
        // Process payment
    }
}

// Bootstrap: discover all listeners once
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: true
);

// Emit events anywhere in your application
Event::emit('order.placed', new OrderDTO(
    orderId: $order->id,
    total: $order->total,
    placedAt: new DateTimeImmutable()
));

Event::emit(OrderEvents::Placed, new OrderDTO(...));
```

## Features at a Glance

- **Attribute-Based Discovery** - Automatic listener registration with zero boilerplate
- **Production Caching** - Parse attributes once, load instantly from cache
- **Zero Dependencies** - No required external packages
- **Framework Agnostic** - Works anywhere PHP runs
- **Type-Safe Events** - Backed enum support
- **Immutable Event Data** - Readonly DTO classes for true event integrity
- **Flexible Registration** - Attributes, manual, or hybrid approaches
- **Priority Control** - Fine-tune listener execution order
- **Wildcard Patterns** - Listen to multiple events with one listener
- **Propagation Control** - Optional short-circuit with boolean returns
- **Error Resilience** - Continue processing even when listeners fail
- **Memory Leak Detection** - Warns about excessive listener counts
- **Container Integration** - PSR-11 support for dependency injection
- **Custom Emitter Support** - Inject your own EventEmitterInterface implementation
- **Testing-Friendly** - Easy to reset, mock, and verify

## Inspiration

This library is inspired by Node.js's EventEmitter API, bringing its elegant event-driven patterns to PHP while adding powerful PHP-specific features:

**From Node.js**:
- Simple `on()` and `emit()` API
- Event name strings for loose coupling
- Priority-based listener ordering
- One-time listeners with `once()`
- Chainable method calls
- Memory leak warnings

**PHP Enhancements**:
- **Attributes** : Declarative listener registration with `#[ListenTo]` and `#[ListenOnce]`
- **Automatic Discovery** : Scan directories and auto-register annotated listeners
- **Smart Caching** : Parse attributes once, load instantly on subsequent requests
- **Backed Enums**: Type-safe event names
- **Wildcard Patterns**: Listen to multiple events with glob-style patterns
- **PSR-11 Integration**: Dependency injection for listener classes
- **Readonly Classes**: Immutable event DTOs at the language level (PHP 8.2+)
- **Trait-Based Composition**: Add events to any class without inheritance
- **Custom Emitters**: Swap implementations for testing or advanced use cases

The result is a familiar API for Node.js developers that feels natural to PHP developers, with powerful modern PHP features that make event-driven architecture more maintainable and type-safe. **The attribute-based discovery system is the standout feature**, eliminating the boilerplate event registration code that plagues other PHP event libraries.

## License

MIT License - see LICENSE file for details

## Contributing

Contributions are welcome! Please see CONTRIBUTING.md for guidelines.