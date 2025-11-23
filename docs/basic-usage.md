# Basic Usage

Core event emission and listener registration patterns.

## Table of Contents

- [Overview](#overview)
- [Event Emission](#event-emission)
  - [Simple Emission](#simple-emission)
  - [Multiple Arguments](#multiple-arguments)
  - [Backed Enums](#backed-enums)
  - [Readonly DTOs](#readonly-dtos)
- [Listener Registration](#listener-registration)
  - [Closures](#closures)
  - [Class Methods](#class-methods)
  - [Invokable Classes](#invokable-classes)
  - [Functions](#functions)
- [Removing Listeners](#removing-listeners)
- [One-Time Listeners](#one-time-listeners)
- [Checking for Listeners](#checking-for-listeners)
- [Usage Patterns](#usage-patterns)
  - [Static Event Facade](#static-event-facade)
  - [EventEmitter Instance](#eventemitter-instance)
  - [EventEmitterTrait](#eventemittertrait)
- [Best Practices](#best-practices)

## Overview

This guide covers manual event emission and listener registration. For most applications, we recommend using [Listener Discovery](listener-discovery.md) with attributes for automatic registration. Use manual registration for:

- Dynamic listeners that need runtime registration
- Quick prototyping or simple applications
- Testing and mocking
- Legacy code integration

## Event Emission

### Simple Emission

Emit events with the `emit()` method. Events are fire-and-forget—you don't receive any return value.

```php
use Rcalicdan\Event\Event;

// Emit an event
Event::emit('user.login');

// Emit with data
Event::emit('user.login', $user);

// Emit with multiple arguments
Event::emit('order.placed', $order, $timestamp);
```

### Multiple Arguments

Pass as many arguments as needed. All registered listeners will receive them.

```php
Event::emit('payment.processed', $payment, $order, $customer);

// Listeners receive all arguments
Event::on('payment.processed', function ($payment, $order, $customer) {
    // Handle payment
});
```

### Backed Enums

Use backed enums for type-safe event names.

```php
enum UserEvents: string {
    case Registered = 'user.registered';
    case Updated = 'user.updated';
    case Deleted = 'user.deleted';
}

// Emit with enum
Event::emit(UserEvents::Registered, $user);

// Listen with enum
Event::on(UserEvents::Registered, function ($user) {
    // Handle registration
});
```

**Benefits**:
- IDE autocomplete
- Refactoring support
- Type safety
- Self-documenting code

### Readonly DTOs

Always pass immutable data to maintain event integrity.

```php
readonly class UserDTO
{
    public function __construct(
        public string $userId,
        public string $email,
        public DateTimeImmutable $registeredAt,
    ) {}
}

// Emit with DTO
Event::emit('user.registered', new UserDTO(
    userId: $user->id,
    email: $user->email,
    registeredAt: new DateTimeImmutable()
));

// Listeners receive immutable data
Event::on('user.registered', function (UserDTO $user) {
    // $user properties cannot be modified
});
```

## Listener Registration

### Closures

The simplest way to register a listener.

```php
Event::on('user.login', function ($user) {
    echo "User {$user->email} logged in";
});

// With type hints
Event::on('order.placed', function (OrderDTO $order) {
    processOrder($order);
});
```

### Class Methods

Register methods from class instances.

```php
class NotificationService
{
    public function sendWelcomeEmail(UserDTO $user)
    {
        // Send email
    }
}

$notifier = new NotificationService();
Event::on('user.registered', [$notifier, 'sendWelcomeEmail']);
```

### Invokable Classes

Use classes with an `__invoke()` method.

```php
class SendWelcomeEmail
{
    public function __invoke(UserDTO $user)
    {
        // Send email
    }
}

Event::on('user.registered', new SendWelcomeEmail());
```

### Functions

Register standalone functions.

```php
function logUserActivity(UserDTO $user): void
{
    error_log("User {$user->userId} logged in");
}

Event::on('user.login', 'logUserActivity');
Event::on('user.login', 'App\Functions\logUserActivity'); // Namespaced
```

## Removing Listeners

Remove specific listeners to prevent memory leaks or stop event handling.

```php
// Store the listener reference
$listener = function ($user) {
    echo "User logged in";
};

Event::on('user.login', $listener);

// Remove it later
Event::removeListener('user.login', $listener);
```

**Important**: The listener reference must be the exact same instance.

```php
// This won't work - different closure instances
Event::on('user.login', function () { echo "Hello"; });
Event::removeListener('user.login', function () { echo "Hello"; }); // Won't remove

// This works - same reference
$listener = function () { echo "Hello"; };
Event::on('user.login', $listener);
Event::removeListener('user.login', $listener); // Removes successfully
```

**Remove All Listeners**:

```php
// Remove all listeners for a specific event
Event::removeAllListeners('user.login');

// Remove all listeners for all events
Event::removeAllListeners();
```

## One-Time Listeners

Register listeners that execute only once, then automatically remove themselves.

```php
Event::once('app.initialized', function () {
    // Runs only the first time
    setupDatabase();
    warmupCache();
});

// Subsequent emits do nothing
Event::emit('app.initialized'); // Listener executes and removes itself
Event::emit('app.initialized'); // Nothing happens
Event::emit('app.initialized'); // Nothing happens
```

**Use Cases**:
- Application initialization
- One-time setup tasks
- Resource allocation
- Event acknowledgment

## Checking for Listeners

Check if an event has any registered listeners.

```php
if (Event::hasListeners('order.placed')) {
    // Someone is listening
    Event::emit('order.placed', $order);
} else {
    // No listeners, skip expensive work
}
```

**Get Listener Count**:

```php
// Count listeners for a specific event
$count = Event::listenerCount('user.login');

// Count all listeners across all events
$totalCount = Event::listenerCount();
```

**Get Event Names**:

```php
// Get all event names that have listeners
$events = Event::eventNames();
// Returns: ['user.login', 'order.placed', 'payment.processed']
```

**Get Listeners**:

```php
// Get all listeners for an event
$listeners = Event::listeners('user.login');
// Returns: [callable, callable, ...]

// Get raw listeners with priority info
$rawListeners = Event::rawListeners('user.login');
// Returns: [
//     ['callback' => callable, 'priority' => 10],
//     ['callback' => callable, 'priority' => 5],
// ]
```

## Usage Patterns

### Static Event Facade

Use the global `Event` class for application-wide events.

```php
use Rcalicdan\Event\Event;

// Register listeners
Event::on('user.registered', function ($user) {
    sendEmail($user);
});

// Emit events
Event::emit('user.registered', $user);

// Configure
Event::setMaxListeners(100);
Event::resilient(); // or Event::failFast()
```

**When to use**: Application-level events that any part of your codebase should access.

### EventEmitter Instance

Create standalone emitter instances for module isolation.

```php
use Rcalicdan\Event\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('data.received', function ($data) {
    processData($data);
});

$emitter->emit('data.received', $payload);

// Each instance is independent
$emitter1 = new EventEmitter();
$emitter2 = new EventEmitter();
$emitter1->on('test', $callback); // Only on emitter1
```

**When to use**: Modules, plugins, or subsystems that need isolated event handling.

### EventEmitterTrait

Add event capabilities to any class.

```php
use Rcalicdan\Event\EventEmitterTrait;

class OrderProcessor
{
    use EventEmitterTrait;
    
    public function process(Order $order)
    {
        // Validate
        if (!$this->validate($order)) {
            $this->emit('order.validation.failed', $order);
            return;
        }
        
        // Process
        $order->markAsPaid();
        $this->emit('order.processed', $order);
    }
    
    private function validate(Order $order): bool
    {
        // Validation logic
        return true;
    }
}

// Usage
$processor = new OrderProcessor();

$processor->on('order.processed', function ($order) {
    echo "Order processed!";
});

$processor->on('order.validation.failed', function ($order) {
    echo "Validation failed!";
});

$processor->process($order);
```

**When to use**: Domain objects or services that need their own event system.

**All Methods Available**:

```php
$processor->on('event', $callback, $priority);
$processor->once('event', $callback, $priority);
$processor->emit('event', ...$args);
$processor->removeListener('event', $callback);
$processor->removeAllListeners('event');
$processor->hasListeners('event');
$processor->listenerCount('event');
$processor->eventNames();
$processor->listeners('event');
$processor->setMaxListeners(100);
```

## Best Practices

### 1. Use Descriptive Event Names

```php
// Good: Clear and specific
Event::emit('user.registered', $user);
Event::emit('order.payment.completed', $order);
Event::emit('email.sent', $email);

// Bad: Vague or unclear
Event::emit('user', $user);
Event::emit('done', $order);
Event::emit('event', $email);
```

### 2. Use Namespaced Event Names

```php
// Organize events by domain
Event::emit('user.registered', $user);
Event::emit('user.updated', $user);
Event::emit('user.deleted', $user);

Event::emit('order.placed', $order);
Event::emit('order.shipped', $order);
Event::emit('order.delivered', $order);
```

### 3. Pass Immutable Data

```php
// Good: Readonly DTO
readonly class UserDTO {
    public function __construct(
        public string $userId,
        public string $email,
    ) {}
}

Event::emit('user.registered', new UserDTO($user->id, $user->email));

// Acceptable: Scalar values
Event::emit('user.login', $user->id, $user->email);

// Bad: Mutable objects
Event::emit('user.registered', $user); // Listeners can modify $user
```

### 4. Validate Before Emitting

```php
// Good: Validation happens first
if (!$validator->validate($data)) {
    throw new ValidationException();
}

$user = User::create($data);
Event::emit('user.registered', new UserDTO($user->id, $user->email));

// Bad: Using events for validation
Event::emit('user.register', $data); // Listeners validate and might stop propagation
```

### 5. Don't Rely on Listener Order

```php
// Bad: Assuming execution order
Event::on('order.placed', function ($order) {
    // Assumes payment was already processed
    shipOrder($order);
});

// Good: Use priority if order matters
Event::on('order.placed', function ($order) {
    processPayment($order);
}, priority: 10); // Runs first

Event::on('order.placed', function ($order) {
    shipOrder($order);
}, priority: 5); // Runs second
```

### 6. Use Once for Initialization

```php
// Good: One-time setup
Event::once('app.booted', function () {
    setupDatabase();
    warmupCache();
});

// Bad: Manual removal
$listener = function () {
    setupDatabase();
    Event::removeListener('app.booted', $listener); // Unnecessary
};
Event::on('app.booted', $listener);
```

### 7. Clean Up Listeners

```php
// In long-running processes or tests
class SomeService
{
    private $listener;
    
    public function __construct()
    {
        $this->listener = function ($data) {
            $this->process($data);
        };
        
        Event::on('data.received', $this->listener);
    }
    
    public function __destruct()
    {
        // Prevent memory leaks
        Event::removeListener('data.received', $this->listener);
    }
}
```

### 8. Use Enums for Type Safety

```php
// Good: Type-safe events
enum OrderEvents: string {
    case Placed = 'order.placed';
    case Shipped = 'order.shipped';
}

Event::on(OrderEvents::Placed, $callback);
Event::emit(OrderEvents::Placed, $order);

// Bad: String literals everywhere
Event::on('order.placed', $callback); // Typo risk
Event::emit('order.palced', $order); // Silent failure
```

### 9. Handle Errors in Listeners

```php
// Resilient mode for production
Event::resilient();

// Create error handler
Event::on('error', function (Throwable $error) {
    logError($error);
    notifyTeam($error);
});

// Listeners can throw safely
Event::on('order.placed', function ($order) {
    if (!$api->sendOrder($order)) {
        throw new ApiException('Failed to send order');
    }
});
```

### 10. Reset in Tests

```php
use PHPUnit\Framework\TestCase;
use Rcalicdan\Event\Event;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean slate for each test
        Event::reset();
    }
    
    public function test_event_emission()
    {
        $captured = null;
        
        Event::on('test.event', function ($data) use (&$captured) {
            $captured = $data;
        });
        
        Event::emit('test.event', 'test-data');
        
        $this->assertEquals('test-data', $captured);
    }
}
```

---

[← Back to Main Documentation](../README.md) | [Previous: Listener Discovery](listener-discovery.md) | [Next: Advanced Features →](advanced-features.md)