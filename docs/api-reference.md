# API Reference

Complete documentation of all classes, methods, and interfaces.

## Table of Contents

- [Event (Static Facade)](#event-static-facade)
- [EventEmitter](#eventemitter)
- [EventEmitterInterface](#eventemitterinterface)
- [EventEmitterTrait](#eventemittertrait)
- [ListenerDiscovery](#listenerdiscovery)
- [Attributes](#attributes)
  - [ListenTo](#listento)
  - [ListenOnce](#listenonce)

---

## Event (Static Facade)

Static facade for global event handling.

**Namespace**: `Rcalicdan\Event\Event`

### Methods

#### getInstance()

Get the shared event emitter instance.

```php
public static function getInstance(): EventEmitterInterface
```

**Returns**: `EventEmitterInterface` - The current event emitter instance

**Example**:
```php
$emitter = Event::getInstance();
```

---

#### setInstance()

Set a custom event emitter instance.

```php
public static function setInstance(EventEmitterInterface $emitter): void
```

**Parameters**:
- `$emitter` - Custom event emitter implementation

**Example**:
```php
Event::setInstance(new CustomEventEmitter());
```

---

#### reset()

Reset the instance and clear all listeners.

```php
public static function reset(): void
```

**Example**:
```php
Event::reset(); // Useful for testing
```

---

#### on()

Attach a callback to an event.

```php
public static function on(
    string|\BackedEnum $event,
    callable $callback,
    int $priority = 0
): EventEmitterInterface
```

**Parameters**:
- `$event` - Event name (string or BackedEnum)
- `$callback` - Callable to execute when event is emitted
- `$priority` - Execution priority (default: 0, higher = earlier)

**Returns**: `EventEmitterInterface` - For method chaining

**Example**:
```php
Event::on('user.login', function ($user) {
    logActivity($user);
}, priority: 10);
```

---

#### once()

Attach a one-time callback to an event.

```php
public static function once(
    string|\BackedEnum $event,
    callable $callback,
    int $priority = 0
): EventEmitterInterface
```

**Parameters**:
- `$event` - Event name (string or BackedEnum)
- `$callback` - Callable to execute once
- `$priority` - Execution priority (default: 0)

**Returns**: `EventEmitterInterface` - For method chaining

**Example**:
```php
Event::once('app.initialized', function () {
    setupDatabase();
});
```

---

#### emit()

Emit an event to all registered listeners.

```php
public static function emit(string|\BackedEnum $event, mixed ...$args): void
```

**Parameters**:
- `$event` - Event name (string or BackedEnum)
- `...$args` - Arguments to pass to listeners

**Returns**: `void`

**Example**:
```php
Event::emit('order.placed', $order, $timestamp);
```

---

#### removeListener()

Remove a specific listener from an event.

```php
public static function removeListener(
    string|\BackedEnum $event,
    callable $callback
): EventEmitterInterface
```

**Parameters**:
- `$event` - Event name
- `$callback` - Listener to remove (must be exact same reference)

**Returns**: `EventEmitterInterface` - For method chaining

**Example**:
```php
$listener = function ($user) { /* ... */ };
Event::on('user.login', $listener);
Event::removeListener('user.login', $listener);
```

---

#### removeAllListeners()

Remove all listeners for a specific event or all events.

```php
public static function removeAllListeners(string|\BackedEnum|null $event = null): void
```

**Parameters**:
- `$event` - Event name, or null to remove all listeners

**Returns**: `void`

**Example**:
```php
Event::removeAllListeners('user.login'); // Remove for specific event
Event::removeAllListeners(); // Remove all listeners
```

---

#### hasListeners()

Check if an event has any listeners.

```php
public static function hasListeners(string|\BackedEnum $event): bool
```

**Parameters**:
- `$event` - Event name

**Returns**: `bool` - True if event has listeners

**Example**:
```php
if (Event::hasListeners('order.placed')) {
    Event::emit('order.placed', $order);
}
```

---

#### listenerCount()

Get the number of listeners for an event or all events.

```php
public static function listenerCount(string|\BackedEnum|null $event = null): int
```

**Parameters**:
- `$event` - Event name, or null to count all listeners

**Returns**: `int` - Number of listeners

**Example**:
```php
$count = Event::listenerCount('user.login');
$totalCount = Event::listenerCount();
```

---

#### eventNames()

Get all event names that have registered listeners.

```php
public static function eventNames(): array
```

**Returns**: `array<int, string>` - Array of event names

**Example**:
```php
$events = Event::eventNames();
// Returns: ['user.login', 'order.placed', ...]
```

---

#### listeners()

Get all listeners for a specific event.

```php
public static function listeners(string|\BackedEnum $event): array
```

**Parameters**:
- `$event` - Event name

**Returns**: `array<int, callable>` - Array of callables

**Example**:
```php
$listeners = Event::listeners('user.login');
```

---

#### rawListeners()

Get raw listeners data including priority information.

```php
public static function rawListeners(string|\BackedEnum $event): array
```

**Parameters**:
- `$event` - Event name

**Returns**: `array<int, array{callback: callable, priority: int}>`

**Example**:
```php
$rawListeners = Event::rawListeners('user.login');
// Returns: [
//     ['callback' => callable, 'priority' => 10],
//     ['callback' => callable, 'priority' => 5],
// ]
```

---

#### setThrowOnListenerError()

Configure whether to throw exceptions from listeners.

```php
public static function setThrowOnListenerError(bool $throw): void
```

**Parameters**:
- `$throw` - True to throw exceptions, false to emit as 'error' events

**Example**:
```php
Event::setThrowOnListenerError(true); // Fail-fast mode
Event::setThrowOnListenerError(false); // Resilient mode
```

---

#### failFast()

Enable fail-fast mode (exceptions throw immediately).

```php
public static function failFast(): void
```

**Example**:
```php
Event::failFast(); // Development mode
```

---

#### resilient()

Enable resilient mode (exceptions emit as 'error' events).

```php
public static function resilient(): void
```

**Example**:
```php
Event::resilient(); // Production mode
```

---

#### setMaxListeners()

Set maximum number of listeners per event before warning.

```php
public static function setMaxListeners(int $max): void
```

**Parameters**:
- `$max` - Maximum listeners (0 = unlimited, default)

**Example**:
```php
Event::setMaxListeners(100);
Event::setMaxListeners(0); // Disable warning
```

---

#### getMaxListeners()

Get the current maximum listeners setting.

```php
public static function getMaxListeners(): int
```

**Returns**: `int` - Current max listeners setting

**Example**:
```php
$max = Event::getMaxListeners();
```

---

## EventEmitter

Standalone event emitter instance.

**Namespace**: `Rcalicdan\Event\EventEmitter`

**Implements**: `EventEmitterInterface`

**Uses**: `EventEmitterTrait`

### Usage

```php
use Rcalicdan\Event\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('data.received', function ($data) {
    processData($data);
});

$emitter->emit('data.received', $payload);
```

### Methods

All methods from `EventEmitterInterface` are available. See [EventEmitterInterface](#eventemitterinterface) for complete method documentation.

---

## EventEmitterInterface

Interface for event emitter implementations.

**Namespace**: `Rcalicdan\Event\EventEmitterInterface`

### Methods

#### on()

Attach a callback to an event.

```php
public function on(
    string|\BackedEnum $event,
    callable $callback,
    int $priority = 0
): self
```

---

#### once()

Attach a one-time callback to an event.

```php
public function once(
    string|\BackedEnum $event,
    callable $callback,
    int $priority = 0
): self
```

---

#### emit()

Broadcast an event to all registered listeners.

```php
public function emit(string|\BackedEnum $event, mixed ...$args): void
```

---

#### removeListener()

Remove a specific listener from an event.

```php
public function removeListener(
    string|\BackedEnum $event,
    callable $callback
): self
```

---

#### removeAllListeners()

Remove all listeners for a specific event or all events.

```php
public function removeAllListeners(string|\BackedEnum|null $event = null): void
```

---

#### hasListeners()

Check if an event has any listeners.

```php
public function hasListeners(string|\BackedEnum $event): bool
```

---

#### listenerCount()

Get the number of listeners for an event or all events.

```php
public function listenerCount(string|\BackedEnum|null $event = null): int
```

---

#### eventNames()

Get all event names that have registered listeners.

```php
public function eventNames(): array
```

**Returns**: `array<int, string>`

---

#### listeners()

Get all listeners for a specific event.

```php
public function listeners(string|\BackedEnum $event): array
```

**Returns**: `array<int, callable>`

---

#### rawListeners()

Get raw listeners data including priority information.

```php
public function rawListeners(string|\BackedEnum $event): array
```

**Returns**: `array<int, array{callback: callable, priority: int}>`

---

#### setThrowOnListenerError()

Configure exception handling behavior.

```php
public function setThrowOnListenerError(bool $throw): self
```

---

#### setMaxListeners()

Set maximum listeners per event.

```php
public function setMaxListeners(int $max): self
```

---

#### getMaxListeners()

Get current max listeners setting.

```php
public function getMaxListeners(): int
```

---

## EventEmitterTrait

Trait providing event emitter functionality.

**Namespace**: `Rcalicdan\Event\EventEmitterTrait`

### Usage

```php
use Rcalicdan\Event\EventEmitterTrait;

class MyClass
{
    use EventEmitterTrait;
    
    public function doSomething()
    {
        $this->emit('something.happened', $data);
    }
}

$obj = new MyClass();
$obj->on('something.happened', function ($data) {
    // Handle event
});
```

### Methods

All methods from `EventEmitterInterface` are available through the trait.

### Properties

**Private Properties** (managed internally):
- `array $listeners` - Registered listeners
- `array $wildcardListeners` - Wildcard pattern listeners
- `int $listenerIdCounter` - Listener ID generator
- `bool|null $throwOnListenerError` - Error handling mode
- `array $sortedEvents` - Cached sorted listener state
- `int $maxListeners` - Maximum listeners per event (default: 0)
- `array $maxListenersWarned` - Tracks which events already warned

---

## ListenerDiscovery

Automatic listener discovery and registration using attributes.

**Namespace**: `Rcalicdan\Event\ListenerDiscovery`

### Methods

#### discover()

Discover and register all listeners in one or more directories.

```php
public static function discover(
    string|array $directory,
    ?bool $failFast = null,
    ?string $cachePath = null,
    bool $refreshCache = false,
    ?ContainerInterface $container = null,
    ?EventEmitterInterface $emitter = null
): void
```

**Parameters**:
- `$directory` - Directory or array of directories to scan
- `$failFast` - True to throw exceptions, false for resilient mode, null to use env
- `$cachePath` - Directory for cache files, null to disable caching
- `$refreshCache` - True to check file changes, false to always use cache
- `$container` - PSR-11 container for dependency injection
- `$emitter` - Custom event emitter instance

**Returns**: `void`

**Example**:
```php
use Rcalicdan\Event\ListenerDiscovery;

ListenerDiscovery::discover(
    directory: [
        __DIR__ . '/src/Listeners',
        __DIR__ . '/src/Subscribers',
    ],
    failFast: false,
    cachePath: __DIR__ . '/var/cache',
    refreshCache: $_ENV['APP_ENV'] === 'development',
    container: $container,
    emitter: new CustomEmitter()
);
```

---

#### reset()

Reset discovery state (useful for testing).

```php
public static function reset(): void
```

**Example**:
```php
ListenerDiscovery::reset();
```

---

## Attributes

### ListenTo

Attribute for registering event listeners.

**Namespace**: `Rcalicdan\Event\Attributes\ListenTo`

**Targets**: Classes, Methods, Functions

**Repeatable**: Yes

#### Constructor

```php
public function __construct(
    string|\BackedEnum $event,
    string $method = 'handle',
    int $priority = 0
)
```

**Parameters**:
- `$event` - Event name (string or BackedEnum)
- `$method` - Method name to call (default: 'handle')
- `$priority` - Execution priority (default: 0)

#### Properties

- `public readonly string|\BackedEnum $event` - Event name
- `public string $method` - Method name
- `public int $priority` - Priority

#### Examples

```php
use Rcalicdan\Event\Attributes\ListenTo;

// Class-level
#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user) { }
}

// With custom method
#[ListenTo('order.placed', method: 'processOrder')]
class OrderProcessor
{
    public function processOrder(OrderDTO $order) { }
}

// With priority
#[ListenTo('payment.received', priority: 10)]
class ProcessPayment
{
    public function handle(PaymentDTO $payment) { }
}

// Method-level
class EventSubscriber
{
    #[ListenTo('order.placed')]
    public function onOrderPlaced(OrderDTO $order) { }
    
    #[ListenTo('order.shipped')]
    public function onOrderShipped(OrderDTO $order) { }
}

// Function-level
#[ListenTo('user.login')]
function logUserActivity(UserDTO $user): void
{
    // Log activity
}

// Multiple attributes
#[ListenTo('order.placed')]
#[ListenTo('order.updated')]
#[ListenTo('order.shipped')]
class OrderLogger
{
    public function handle(OrderDTO $order) { }
}

// With backed enums
enum UserEvents: string {
    case Registered = 'user.registered';
}

#[ListenTo(UserEvents::Registered)]
class NotifyAdmin
{
    public function handle(UserDTO $user) { }
}
```

---

### ListenOnce

Attribute for registering one-time event listeners.

**Namespace**: `Rcalicdan\Event\Attributes\ListenOnce`

**Targets**: Classes, Methods, Functions

**Repeatable**: Yes

#### Constructor

```php
public function __construct(
    string|\BackedEnum $event,
    string $method = 'handle',
    int $priority = 0
)
```

**Parameters**:
- `$event` - Event name (string or BackedEnum)
- `$method` - Method name to call (default: 'handle')
- `$priority` - Execution priority (default: 0)

#### Properties

- `public readonly string|\BackedEnum $event` - Event name
- `public string $method` - Method name
- `public int $priority` - Priority

#### Examples

```php
use Rcalicdan\Event\Attributes\ListenOnce;

// Class-level
#[ListenOnce('app.initialized')]
class SetupDatabase
{
    public function handle()
    {
        // Runs only once
        $this->runMigrations();
    }
}

// Method-level
class AppBootstrap
{
    #[ListenOnce('app.booted')]
    public function warmupCache()
    {
        // One-time cache warming
    }
    
    #[ListenOnce('app.booted')]
    public function connectServices()
    {
        // One-time service connection
    }
}

// Function-level
#[ListenOnce('database.connected')]
function createTables(): void
{
    // Runs only once
}

// With priority
#[ListenOnce('app.initialized', priority: 100)]
class CriticalSetup
{
    public function handle()
    {
        // Runs first, only once
    }
}
```

---

## Type Definitions

### BackedEnum

PHP 8.1+ backed enumerations for type-safe event names.

```php
enum UserEvents: string {
    case Registered = 'user.registered';
    case Updated = 'user.updated';
    case Deleted = 'user.deleted';
}

enum OrderEvents: string {
    case Placed = 'order.placed';
    case Shipped = 'order.shipped';
    case Delivered = 'order.delivered';
}
```

**Usage**:
```php
Event::on(UserEvents::Registered, $callback);
Event::emit(UserEvents::Registered, $user);

#[ListenTo(OrderEvents::Placed)]
class ProcessOrder { }
```

---

## PSR-11 Container

The library supports PSR-11 containers for dependency injection.

**Interface**: `Psr\Container\ContainerInterface`

### Required Methods

```php
public function get(string $id): mixed
public function has(string $id): bool
```

### Compatible Containers

- PHP-DI
- Symfony DependencyInjection
- Laravel Container
- Pimple
- Aura.Di
- Any PSR-11 compliant container

### Usage

```php
use Psr\Container\ContainerInterface;

$container = new YourContainer();

ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    container: $container
);
```

---

[← Back to Main Documentation](../README.md) | [Previous: Advanced Features](advanced-features.md) | [Next: Best Practices →](best-practices.md)