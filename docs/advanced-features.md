# Advanced Features

Priority control, wildcard patterns, error handling, and performance optimization.

## Table of Contents

- [Listener Priorities](#listener-priorities)
  - [How Priorities Work](#how-priorities-work)
  - [Priority Guidelines](#priority-guidelines)
  - [Combining Priorities](#combining-priorities)
- [Wildcard Patterns](#wildcard-patterns)
  - [Basic Wildcards](#basic-wildcards)
  - [Pattern Matching](#pattern-matching)
  - [Wildcard Use Cases](#wildcard-use-cases)
- [Propagation Control](#propagation-control)
  - [Stopping Propagation](#stopping-propagation)
  - [When to Use](#when-to-use)
  - [Anti-Patterns](#anti-patterns)
- [Error Handling](#error-handling)
  - [Fail-Fast Mode](#fail-fast-mode)
  - [Resilient Mode](#resilient-mode)
  - [Error Event Listeners](#error-event-listeners)
  - [Environment-Based Configuration](#environment-based-configuration)
- [Memory Management](#memory-management)
  - [Max Listeners Warning](#max-listeners-warning)
  - [Removing Listeners](#removing-listeners)
  - [Reset State](#reset-state)
- [Custom Event Emitters](#custom-event-emitters)
  - [Implementing Custom Emitters](#implementing-custom-emitters)
  - [Use Cases](#use-cases)
- [Performance Optimization](#performance-optimization)

## Listener Priorities

### How Priorities Work

Priorities control the execution order of listeners. Higher priority listeners execute first.

```php
use Rcalicdan\Event\Event;

Event::on('order.placed', function ($order) {
    echo "Third (priority: 0)\n";
}, priority: 0);

Event::on('order.placed', function ($order) {
    echo "First (priority: 100)\n";
}, priority: 100);

Event::on('order.placed', function ($order) {
    echo "Second (priority: 50)\n";
}, priority: 50);

Event::emit('order.placed', $order);

// Output:
// First (priority: 100)
// Second (priority: 50)
// Third (priority: 0)
```

**Default Priority**: `0`

**Priority Range**: Any integer (positive or negative)

### Priority Guidelines

Use priorities to control logical flow when order matters.

```php
// Critical: Validation and authorization (100+)
Event::on('order.placed', function ($order) {
    validateOrder($order);
}, priority: 100);

// High: Core business logic (50-99)
Event::on('order.placed', function ($order) {
    processPayment($order);
}, priority: 80);

Event::on('order.placed', function ($order) {
    reserveInventory($order);
}, priority: 70);

// Medium: Side effects (10-49)
Event::on('order.placed', function ($order) {
    sendConfirmationEmail($order);
}, priority: 30);

Event::on('order.placed', function ($order) {
    notifyWarehouse($order);
}, priority: 20);

// Low: Logging and analytics (0 or negative)
Event::on('order.placed', function ($order) {
    logOrderEvent($order);
}, priority: 0);

Event::on('order.placed', function ($order) {
    trackAnalytics($order);
}, priority: -10);
```

### Combining Priorities

Priorities work with all registration methods.

```php
// Manual registration
Event::on('event', $callback, priority: 10);
Event::once('event', $callback, priority: 5);

// With attributes
#[ListenTo('order.placed', priority: 100)]
class ValidateOrder { }

#[ListenTo('order.placed', priority: 50)]
class ProcessPayment { }

// With trait
$emitter = new OrderProcessor();
$emitter->on('order.processed', $callback, priority: 10);
```

## Wildcard Patterns

### Basic Wildcards

Use `*` to match multiple events with a single listener.

```php
// Listen to all user events
Event::on('user.*', function ($data) {
    logUserEvent($data);
});

// Matches:
Event::emit('user.registered', $user);
Event::emit('user.updated', $user);
Event::emit('user.deleted', $user);
Event::emit('user.login', $user);
Event::emit('user.logout', $user);
```

**Match All Events**:

```php
// Listen to every event in the system
Event::on('*', function (...$args) {
    logAllEvents($args);
});
```

### Pattern Matching

Wildcards support glob-style patterns.

```php
// Match specific patterns
Event::on('order.*', $handler);        // All order events
Event::on('payment.*', $handler);      // All payment events
Event::on('*.created', $handler);      // All creation events
Event::on('*.deleted', $handler);      // All deletion events

// Multiple wildcards
Event::on('user.*.success', $handler); // user.login.success, user.register.success
```

**Examples**:

```php
// Domain-specific logging
Event::on('order.*', function ($data) {
    Log::info("Order event: " . json_encode($data));
});

// Track all creation events
Event::on('*.created', function ($data) {
    Analytics::track('entity_created', $data);
});

// Monitor all errors
Event::on('*.error', function ($error) {
    ErrorTracker::capture($error);
});
```

### Wildcard Use Cases

**1. Cross-Cutting Concerns**:

```php
// Logging
Event::on('*', function (...$args) {
    Logger::debug('Event emitted', ['args' => $args]);
});

// Metrics
Event::on('*', function () {
    Metrics::increment('events.total');
});
```

**2. Domain Monitoring**:

```php
// Monitor all user activity
Event::on('user.*', function ($user) {
    ActivityMonitor::track('user', $user->userId);
});

// Track all payments
Event::on('payment.*', function ($payment) {
    PaymentAudit::log($payment);
});
```

**3. Development Debugging**:

```php
// In development, log all events
if ($_ENV['APP_ENV'] === 'development') {
    Event::on('*', function (...$args) {
        var_dump('Event:', $args);
    });
}
```

**4. Event Aggregation**:

```php
// Aggregate statistics
Event::on('order.*', function () {
    Cache::increment('stats:orders:today');
});

Event::on('user.registered', function () {
    Cache::increment('stats:users:today');
});
```

**Note**: Wildcard listeners execute after exact matches, sorted by priority.

## Propagation Control

### Stopping Propagation

Listeners can stop event propagation by returning `false`.

```php
Event::on('cache.lookup', function ($key) {
    if (Cache::has($key)) {
        return false; // Stop propagation
    }
}, priority: 10);

Event::on('cache.lookup', function ($key) {
    // This won't execute if cache hit above
    Cache::rebuild($key);
}, priority: 5);
```

**How It Works**:

```php
Event::on('event', function () {
    echo "First\n";
    return false; // Stop here
}, priority: 10);

Event::on('event', function () {
    echo "Second\n"; // Never executes
}, priority: 5);

Event::emit('event');
// Output: First
```

### When to Use

Use propagation control sparingly, only for performance optimization.

**Acceptable Use Cases**:

```php
// Cache short-circuit
Event::on('data.fetch', function ($id) {
    if ($cached = Cache::get($id)) {
        return false; // Don't fetch from database
    }
}, priority: 100);

Event::on('data.fetch', function ($id) {
    // Expensive database query
    return Database::find($id);
}, priority: 50);

// Circuit breaker
Event::on('api.call', function ($endpoint) {
    if (CircuitBreaker::isOpen($endpoint)) {
        return false; // Don't call failing API
    }
}, priority: 100);
```

### Anti-Patterns

**Don't use propagation control for validation or authorization**:

```php
// BAD: Using events for validation
Event::on('user.register', function ($data) {
    if (!$validator->validate($data)) {
        return false; // Don't do this!
    }
});

// GOOD: Validate before emitting
if (!$validator->validate($data)) {
    throw new ValidationException();
}
$user = User::create($data);
Event::emit('user.registered', $user);
```

**Don't use it for business logic**:

```php
// BAD: Business logic in propagation
Event::on('order.process', function ($order) {
    if ($order->total < 10) {
        return false; // Skip processing? Bad idea!
    }
});

// GOOD: Handle logic explicitly
if ($order->total >= 10) {
    processOrder($order);
    Event::emit('order.processed', $order);
}
```

## Error Handling

### Fail-Fast Mode

Exceptions thrown by listeners propagate immediately.

```php
use Rcalicdan\Event\Event;

// Enable fail-fast mode
Event::failFast();
// or
Event::setThrowOnListenerError(true);

Event::on('order.placed', function ($order) {
    throw new Exception('Payment failed');
});

try {
    Event::emit('order.placed', $order);
} catch (Exception $e) {
    // Exception is thrown
    echo "Caught: " . $e->getMessage();
}
```

**Use Case**: Development and testing to catch errors immediately.

### Resilient Mode

Exceptions are caught and emitted as `'error'` events. Other listeners continue executing.

```php
// Enable resilient mode
Event::resilient();
// or
Event::setThrowOnListenerError(false);

Event::on('order.placed', function ($order) {
    throw new Exception('Listener failed');
});

Event::on('order.placed', function ($order) {
    echo "This still executes\n";
});

Event::emit('order.placed', $order);
// Both listeners execute, exception doesn't stop execution
```

**Use Case**: Production to keep application running even when listeners fail.

### Error Event Listeners

In resilient mode, handle errors by listening to the `'error'` event.

```php
Event::resilient();

// Handle all listener errors
Event::on('error', function (Throwable $error) {
    // Log the error
    error_log($error->getMessage());
    
    // Track in monitoring service
    Sentry::captureException($error);
});

// This listener throws
Event::on('user.registered', function ($user) {
    throw new Exception('Email service down');
});

// Error is caught and emitted as 'error' event
Event::emit('user.registered', $user);
```

**With Attributes**:

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

**Important**: If the error handler itself throws an exception in resilient mode, the library writes the error to `STDERR` to prevent infinite loops.

### Environment-Based Configuration

Configure error handling based on environment.

```php
// Via environment variable
// .env file:
// EVENT_THROW_ON_ERROR=true

Event::setThrowOnListenerError(null); // Uses ENV var

// Or programmatically
$isDev = $_ENV['APP_ENV'] === 'development';

if ($isDev) {
    Event::failFast();
} else {
    Event::resilient();
}

// Or in discovery
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    failFast: $_ENV['APP_ENV'] === 'development'
);
```

## Memory Management

### Max Listeners Warning

Set a limit to detect potential memory leaks.

```php
// Set maximum listeners per event
Event::setMaxListeners(100);

// Get current setting
$max = Event::getMaxListeners(); // Returns: 100

// Disable warning (default)
Event::setMaxListeners(0);
```

**Default**: `0` (no limit, warnings disabled)

**How It Works**:

When the number of listeners exceeds the limit, a warning is emitted:

```php
Event::setMaxListeners(2);

Event::on('test', $callback1);
Event::on('test', $callback2);
Event::on('test', $callback3); // Triggers warning

// Warning: Possible EventEmitter memory leak detected.
// 3 listeners added for event "test".
// Use setMaxListeners() to increase limit or set to 0 to disable this warning.
```

**Use Case**: Detect listener leaks in long-running processes (daemons, workers, WebSocket servers).

**Recommended Values**:

```php
// Development: Set a reasonable limit
Event::setMaxListeners(50);

// Production: Monitor but allow more
Event::setMaxListeners(100);

// Testing: Disable to avoid noise
Event::setMaxListeners(0);

// Long-running processes: Low limit to catch leaks
Event::setMaxListeners(20);
```

### Removing Listeners

Prevent memory leaks by removing listeners when done.

```php
class TemporaryProcessor
{
    private $listener;
    
    public function start()
    {
        $this->listener = function ($data) {
            $this->process($data);
        };
        
        Event::on('data.received', $this->listener);
    }
    
    public function stop()
    {
        // Clean up
        Event::removeListener('data.received', $this->listener);
    }
}
```

**Remove All Listeners**:

```php
// Remove all for specific event
Event::removeAllListeners('user.login');

// Remove all for all events
Event::removeAllListeners();
```

### Reset State

Reset the entire event system (useful for testing).

```php
// Reset to clean state
Event::reset();

// All listeners removed
// All configuration reset
// New EventEmitter instance created
```

## Custom Event Emitters

### Implementing Custom Emitters

Create custom emitters by implementing `EventEmitterInterface`.

```php
use Rcalicdan\Event\EventEmitterInterface;

class AsyncEventEmitter implements EventEmitterInterface
{
    private array $queue = [];
    
    public function on(string|\BackedEnum $event, callable $callback, int $priority = 0): self
    {
        // Custom implementation
        return $this;
    }
    
    public function once(string|\BackedEnum $event, callable $callback, int $priority = 0): self
    {
        // Custom implementation
        return $this;
    }
    
    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        // Queue for async processing
        $this->queue[] = ['event' => $event, 'args' => $args];
    }
    
    public function processQueue(): void
    {
        // Process queued events asynchronously
        foreach ($this->queue as $item) {
            $this->processAsync($item['event'], ...$item['args']);
        }
        $this->queue = [];
    }
    
    // Implement remaining interface methods...
}
```

**Use Custom Emitter**:

```php
use Rcalicdan\Event\Event;

// Set globally
Event::setInstance(new AsyncEventEmitter());

// Or with discovery
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    emitter: new AsyncEventEmitter()
);
```

### Use Cases

**1. Async Processing**:

```php
class QueuedEventEmitter implements EventEmitterInterface
{
    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        // Push to queue instead of immediate execution
        Queue::push(new ProcessEventJob($event, $args));
    }
}
```

**2. Distributed Events**:

```php
class DistributedEventEmitter implements EventEmitterInterface
{
    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        // Emit locally
        $this->emitLocal($event, ...$args);
        
        // Broadcast to other nodes
        MessageBroker::publish('events', [
            'event' => $event,
            'args' => $args,
        ]);
    }
}
```

**3. Event Sourcing**:

```php
class EventStoreEmitter implements EventEmitterInterface
{
    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        // Store event
        EventStore::append($event, $args);
        
        // Emit normally
        $this->emitLocal($event, ...$args);
    }
}
```

**4. Testing**:

```php
class SpyEventEmitter implements EventEmitterInterface
{
    private array $emittedEvents = [];
    
    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        $this->emittedEvents[] = ['event' => $event, 'args' => $args];
    }
    
    public function getEmittedEvents(): array
    {
        return $this->emittedEvents;
    }
}

// In tests
$spy = new SpyEventEmitter();
Event::setInstance($spy);

// Perform actions
processOrder($order);

// Assert events were emitted
$this->assertCount(1, $spy->getEmittedEvents());
$this->assertEquals('order.placed', $spy->getEmittedEvents()[0]['event']);
```

## Performance Optimization

**1. Use Listener Discovery Caching**:

```php
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: false // Production
);
```

**2. Check Before Emitting Expensive Events**:

```php
if (Event::hasListeners('expensive.event')) {
    $expensiveData = computeExpensiveData();
    Event::emit('expensive.event', $expensiveData);
}
```

**3. Use Wildcard Listeners Sparingly**:

```php
// Wildcards are slower than exact matches
Event::on('*', $handler); // Slower
Event::on('user.login', $handler); // Faster
```

**4. Remove Unused Listeners**:

```php
// In long-running processes
Event::removeListener('temp.event', $tempListener);
```

**5. Set Appropriate Max Listeners**:

```php
// Detect memory leaks early in long-running processes
Event::setMaxListeners(100);
```

**6. Use Propagation Control for Expensive Operations**:

```php
Event::on('data.fetch', function ($id) {
    if ($cached = Cache::get($id)) {
        return false; // Skip expensive DB query
    }
}, priority: 100);

Event::on('data.fetch', function ($id) {
    return Database::query($id); // Only runs if not cached
}, priority: 50);
```

---

[← Back to Main Documentation](../README.md) | [Previous: Basic Usage](basic-usage.md) | [Next: API Reference →](api-reference.md)