```markdown
# Best Practices

Patterns, anti-patterns, and architectural guidance for building maintainable event-driven systems.

## Table of Contents

- [Event Design](#event-design)
  - [Naming Conventions](#naming-conventions)
  - [Event Granularity](#event-granularity)
  - [Event Data](#event-data)
- [Listener Design](#listener-design)
  - [Single Responsibility](#single-responsibility)
  - [Idempotency](#idempotency)
  - [Error Handling](#error-handling)
- [Architecture Patterns](#architecture-patterns)
  - [Domain Events](#domain-events)
  - [Event Sourcing](#event-sourcing)
  - [CQRS Integration](#cqrs-integration)
- [Performance](#performance)
  - [Optimization Strategies](#optimization-strategies)
  - [Avoiding Bottlenecks](#avoiding-bottlenecks)
- [Testing](#testing)
  - [Unit Testing Listeners](#unit-testing-listeners)
  - [Integration Testing Events](#integration-testing-events)
  - [Testing with Discovery](#testing-with-discovery)
  - [Testing Error Handling](#testing-error-handling)
  - [Testing with Datasets](#testing-with-datasets)
- [Common Anti-Patterns](#common-anti-patterns)
- [Production Checklist](#production-checklist)

---

## Event Design

### Naming Conventions

Use clear, consistent naming that reflects what happened (past tense).

**Good Naming**:

```php
// Past tense: describes what happened
Event::emit('user.registered', $user);
Event::emit('order.placed', $order);
Event::emit('payment.completed', $payment);
Event::emit('email.sent', $email);

// Namespaced by domain
Event::emit('user.password.reset', $user);
Event::emit('order.payment.failed', $order);
Event::emit('inventory.stock.updated', $item);
```

**Bad Naming**:

```php
// Present tense or commands
Event::emit('register.user', $user);     // Sounds like a command
Event::emit('placing.order', $order);    // Present continuous
Event::emit('pay', $payment);            // Imperative

// Vague or unclear
Event::emit('event', $data);
Event::emit('process', $data);
Event::emit('handle', $data);
```

**Naming Patterns**:

```php
// Pattern: {domain}.{entity}.{action}
Event::emit('user.account.activated', $user);
Event::emit('order.payment.received', $order);
Event::emit('product.inventory.depleted', $product);

// Pattern: {domain}.{action}
Event::emit('user.registered', $user);
Event::emit('order.cancelled', $order);
Event::emit('payment.refunded', $payment);

// Use enums for consistency
enum UserEvents: string {
    case Registered = 'user.registered';
    case EmailVerified = 'user.email.verified';
    case PasswordReset = 'user.password.reset';
    case AccountDeactivated = 'user.account.deactivated';
}
```

### Event Granularity

Events should be atomic and represent single facts.

**Good Granularity**:

```php
// Multiple specific events
Event::emit('order.placed', $order);
Event::emit('inventory.reserved', $order->items);
Event::emit('payment.authorized', $payment);
Event::emit('customer.notified', $customer);

// Each event represents one fact
```

**Bad Granularity**:

```php
// Too coarse: one event for multiple actions
Event::emit('order.processed', [
    'order' => $order,
    'inventory_reserved' => true,
    'payment_authorized' => true,
    'customer_notified' => true,
]);

// Too fine: excessive fragmentation
Event::emit('order.validation.step1.completed', $order);
Event::emit('order.validation.step2.completed', $order);
Event::emit('order.validation.step3.completed', $order);
// Better: Event::emit('order.validated', $order);
```

**Guidelines**:

```php
// One event per business fact
class OrderService
{
    public function placeOrder(Order $order)
    {
        // Validate
        $this->validator->validate($order);
        
        // Process
        $order->markAsPlaced();
        Event::emit('order.placed', new OrderDTO($order));
        
        // Reserve inventory (separate concern)
        $this->inventory->reserve($order->items);
        Event::emit('inventory.reserved', new InventoryDTO($order->items));
        
        // Authorize payment (separate concern)
        $payment = $this->payment->authorize($order);
        Event::emit('payment.authorized', new PaymentDTO($payment));
    }
}
```

### Event Data

Always pass immutable data to preserve event integrity.

**Good Event Data**:

```php
// Readonly DTOs (recommended)
readonly class UserDTO
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $name,
        public DateTimeImmutable $registeredAt,
    ) {}
}

Event::emit('user.registered', new UserDTO(
    userId: $user->id,
    email: $user->email,
    name: $user->name,
    registeredAt: new DateTimeImmutable()
));

// Value objects
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency
    ) {}
}

Event::emit('payment.received', new Money(10000, 'USD'));

// Scalar values for simple data
Event::emit('user.login', $userId, $ipAddress, $timestamp);
```

**Bad Event Data**:

```php
// Mutable objects
Event::emit('user.registered', $user); // Listeners can modify $user

// Complex nested arrays
Event::emit('order.placed', [
    'order' => [
        'id' => 123,
        'items' => [/* ... */],
        'customer' => [/* ... */],
        // Deep nesting, hard to work with
    ]
]);

// Passing entire entities with methods
Event::emit('order.placed', $order); // $order has save(), delete(), etc.
```

**Include Relevant Context**:

```php
// Good: Complete context
readonly class OrderPlacedDTO
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public float $total,
        public int $itemCount,
        public DateTimeImmutable $placedAt,
        public string $ipAddress,
    ) {}
}

// Bad: Insufficient context
Event::emit('order.placed', $orderId); // Listeners need more info
```

---

## Listener Design

### Single Responsibility

Each listener should do one thing well.

**Good Design**:

```php
// Each listener has one job
#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        $this->mailer->send($user->email, 'Welcome!');
    }
}

#[ListenTo('user.registered')]
class CreateUserProfile
{
    public function handle(UserDTO $user)
    {
        $this->profiles->create(['user_id' => $user->userId]);
    }
}

#[ListenTo('user.registered')]
class LogRegistration
{
    public function handle(UserDTO $user)
    {
        $this->logger->info('User registered', ['user_id' => $user->userId]);
    }
}
```

**Bad Design**:

```php
// One listener doing too much
#[ListenTo('user.registered')]
class UserRegistrationHandler
{
    public function handle(UserDTO $user)
    {
        // Too many responsibilities
        $this->mailer->send($user->email, 'Welcome!');
        $this->profiles->create(['user_id' => $user->userId]);
        $this->logger->info('User registered');
        $this->analytics->track('user_registered');
        $this->notifications->notifyAdmins($user);
        $this->rewards->grantSignupBonus($user);
        $this->newsletter->subscribe($user);
    }
}
```

### Idempotency

Listeners should be safe to execute multiple times with the same input.

**Idempotent Listeners**:

```php
#[ListenTo('order.placed')]
class ReserveInventory
{
    public function handle(OrderDTO $order)
    {
        // Check if already reserved
        if ($this->inventory->isReserved($order->orderId)) {
            return; // Already done, safe to skip
        }
        
        $this->inventory->reserve($order->orderId, $order->items);
    }
}

#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        // Check if already sent
        if ($this->emailLog->wasSent($user->userId, 'welcome')) {
            return;
        }
        
        $this->mailer->send($user->email, 'Welcome!');
        $this->emailLog->markSent($user->userId, 'welcome');
    }
}
```

**Non-Idempotent (Problematic)**:

```php
#[ListenTo('order.placed')]
class IncrementStats
{
    public function handle(OrderDTO $order)
    {
        // Problem: increments every time event is emitted
        $this->stats->increment('orders_today');
        // If event is re-emitted, stats are wrong
    }
}

// Better approach
#[ListenTo('order.placed')]
class IncrementStats
{
    public function handle(OrderDTO $order)
    {
        // Use unique identifier to prevent duplicates
        $key = "order_counted:{$order->orderId}";
        
        if (!$this->cache->has($key)) {
            $this->stats->increment('orders_today');
            $this->cache->set($key, true, 86400); // 24 hours
        }
    }
}
```

### Error Handling

Listeners should handle their own errors gracefully.

**Good Error Handling**:

```php
#[ListenTo('order.placed')]
class NotifyWarehouse
{
    public function handle(OrderDTO $order)
    {
        try {
            $this->warehouseApi->sendOrder($order);
        } catch (ApiException $e) {
            // Log the error
            $this->logger->error('Failed to notify warehouse', [
                'order_id' => $order->orderId,
                'error' => $e->getMessage(),
            ]);
            
            // Queue for retry
            $this->retryQueue->add('warehouse.notify', $order);
            
            // Don't throw - let other listeners continue
        }
    }
}

#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        try {
            $this->mailer->send($user->email, 'Welcome!');
        } catch (MailerException $e) {
            // Degrade gracefully
            $this->logger->warning('Welcome email failed', [
                'user_id' => $user->userId,
                'error' => $e->getMessage(),
            ]);
            
            // Don't throw for non-critical email
        }
    }
}
```

**Bad Error Handling**:

```php
#[ListenTo('order.placed')]
class NotifyWarehouse
{
    public function handle(OrderDTO $order)
    {
        // Problem: uncaught exception breaks event flow
        $this->warehouseApi->sendOrder($order);
    }
}

#[ListenTo('user.registered')]
class SendWelcomeEmail
{
    public function handle(UserDTO $user)
    {
        // Problem: swallowing errors without logging
        try {
            $this->mailer->send($user->email, 'Welcome!');
        } catch (MailerException $e) {
            // Silent failure - no one knows this failed
        }
    }
}
```

---

## Architecture Patterns

### Domain Events

Emit events from your domain layer to maintain loose coupling.

```php
// Domain Entity
class Order
{
    use EventEmitterTrait;
    
    public function place(): void
    {
        $this->status = OrderStatus::Placed;
        $this->placedAt = new DateTimeImmutable();
        
        // Emit domain event
        $this->emit('order.placed', new OrderDTO(
            orderId: $this->id,
            customerId: $this->customerId,
            total: $this->total,
            placedAt: $this->placedAt
        ));
    }
    
    public function ship(): void
    {
        $this->status = OrderStatus::Shipped;
        $this->shippedAt = new DateTimeImmutable();
        
        $this->emit('order.shipped', new OrderDTO(/* ... */));
    }
}

// Application Service
class OrderService
{
    public function __construct(
        private OrderRepository $orders
    ) {}
    
    public function placeOrder(PlaceOrderCommand $command): Order
    {
        $order = Order::create($command);
        $order->place(); // Emits 'order.placed' event
        
        $this->orders->save($order);
        
        return $order;
    }
}

// Listeners react to domain events
#[ListenTo('order.placed')]
class SendOrderConfirmation
{
    public function handle(OrderDTO $order)
    {
        $this->mailer->sendOrderConfirmation($order);
    }
}

#[ListenTo('order.placed')]
class UpdateInventory
{
    public function handle(OrderDTO $order)
    {
        $this->inventory->reserve($order);
    }
}
```

### Event Sourcing

Use events as the source of truth.

```php
// Event Store
class EventStore
{
    public function append(string $streamId, object $event): void
    {
        $this->db->insert('event_store', [
            'stream_id' => $streamId,
            'event_type' => get_class($event),
            'event_data' => json_encode($event),
            'occurred_at' => new DateTimeImmutable(),
        ]);
    }
    
    public function getStream(string $streamId): array
    {
        return $this->db->select('event_store', [
            'stream_id' => $streamId
        ]);
    }
}

// Aggregate Root
class Order
{
    private array $uncommittedEvents = [];
    
    public function place(): void
    {
        $event = new OrderPlacedEvent(
            orderId: $this->id,
            customerId: $this->customerId,
            total: $this->total,
            placedAt: new DateTimeImmutable()
        );
        
        $this->apply($event);
        $this->uncommittedEvents[] = $event;
    }
    
    private function apply(OrderPlacedEvent $event): void
    {
        $this->status = OrderStatus::Placed;
        $this->placedAt = $event->placedAt;
    }
    
    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }
    
    public static function reconstitute(array $events): self
    {
        $order = new self();
        
        foreach ($events as $event) {
            $order->apply($event);
        }
        
        return $order;
    }
}

// Repository
class OrderRepository
{
    public function save(Order $order): void
    {
        foreach ($order->getUncommittedEvents() as $event) {
            $this->eventStore->append("order-{$order->id}", $event);
            
            // Emit for listeners
            Event::emit(get_class($event), $event);
        }
    }
    
    public function find(string $orderId): Order
    {
        $events = $this->eventStore->getStream("order-{$orderId}");
        
        return Order::reconstitute($events);
    }
}
```

### CQRS Integration

Separate commands from queries using events.

```php
// Command Side
class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $command): void
    {
        $order = Order::create($command);
        $order->place();
        
        $this->orders->save($order);
        
        // Event emitted by save()
    }
}

// Query Side - Listen to events and update read models
#[ListenTo('order.placed')]
class UpdateOrderReadModel
{
    public function handle(OrderPlacedEvent $event)
    {
        $this->readModel->insert([
            'order_id' => $event->orderId,
            'customer_id' => $event->customerId,
            'total' => $event->total,
            'status' => 'placed',
            'placed_at' => $event->placedAt,
        ]);
    }
}

#[ListenTo('order.shipped')]
class UpdateOrderReadModel
{
    public function handle(OrderShippedEvent $event)
    {
        $this->readModel->update($event->orderId, [
            'status' => 'shipped',
            'shipped_at' => $event->shippedAt,
        ]);
    }
}

// Query Service (reads from read model)
class OrderQueryService
{
    public function findByCustomer(string $customerId): array
    {
        return $this->readModel->where('customer_id', $customerId)->get();
    }
}
```

---

## Performance

### Optimization Strategies

**1. Use Caching for Discovery**:

```php
// Production configuration
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: false // Never check file changes
);
```

**2. Check Before Expensive Operations**:

```php
// Avoid computing expensive data if no one is listening
if (Event::hasListeners('analytics.report')) {
    $expensiveReport = $this->generateReport();
    Event::emit('analytics.report', $expensiveReport);
}
```

**3. Use Priorities for Critical Listeners**:

```php
// High priority for critical operations
#[ListenTo('order.placed', priority: 100)]
class ValidatePayment { }

// Low priority for non-critical
#[ListenTo('order.placed', priority: 0)]
class LogOrderEvent { }
```

**4. Defer Heavy Processing**:

```php
#[ListenTo('order.placed')]
class ProcessOrderAnalytics
{
    public function handle(OrderDTO $order)
    {
        // Queue for background processing instead of immediate execution
        Queue::push(new AnalyzeOrderJob($order));
    }
}
```

### Avoiding Bottlenecks

**Don't Block on External Services**:

```php
// Bad: Blocking synchronous call
#[ListenTo('order.placed')]
class NotifyWarehouse
{
    public function handle(OrderDTO $order)
    {
        // Blocks event processing
        $this->warehouseApi->sendOrder($order);
    }
}

// Good: Queue for async processing
#[ListenTo('order.placed')]
class NotifyWarehouse
{
    public function handle(OrderDTO $order)
    {
        Queue::push(new NotifyWarehouseJob($order));
    }
}
```

**Limit Wildcard Listeners**:

```php
// Bad: Too many wildcard listeners
Event::on('*', $heavyLogger);
Event::on('*', $heavyAnalytics);
Event::on('*', $heavyAuditor);

// Good: Specific listeners
Event::on('order.*', $orderLogger);
Event::on('user.*', $userLogger);
Event::on('payment.*', $paymentLogger);
```

---

## Testing

### Unit Testing Listeners

**PHPUnit Example:**

```php
use PHPUnit\Framework\TestCase;

class SendWelcomeEmailTest extends TestCase
{
    public function test_sends_welcome_email_to_user()
    {
        $mailer = $this->createMock(MailerService::class);
        $listener = new SendWelcomeEmail($mailer);
        
        $user = new UserDTO(
            userId: '123',
            email: 'user@example.com',
            name: 'John Doe',
            registeredAt: new DateTimeImmutable()
        );
        
        $mailer->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo('user@example.com'),
                $this->equalTo('Welcome!')
            );
        
        $listener->handle($user);
    }
}
```

**Pest Example:**

```php
use Rcalicdan\Event\Event;

test('sends welcome email to user', function () {
    $mailer = Mockery::mock(MailerService::class);
    $listener = new SendWelcomeEmail($mailer);
    
    $user = new UserDTO(
        userId: '123',
        email: 'user@example.com',
        name: 'John Doe',
        registeredAt: new DateTimeImmutable()
    );
    
    $mailer->shouldReceive('send')
        ->once()
        ->with('user@example.com', 'Welcome!');
    
    $listener->handle($user);
});

it('logs error when email fails', function () {
    $mailer = Mockery::mock(MailerService::class);
    $logger = Mockery::mock(LoggerInterface::class);
    $listener = new SendWelcomeEmail($mailer, $logger);
    
    $user = new UserDTO(
        userId: '123',
        email: 'user@example.com',
        name: 'John Doe',
        registeredAt: new DateTimeImmutable()
    );
    
    $mailer->shouldReceive('send')
        ->andThrow(new MailerException('SMTP error'));
    
    $logger->shouldReceive('warning')
        ->once()
        ->with('Welcome email failed', Mockery::type('array'));
    
    $listener->handle($user);
});
```

### Integration Testing Events

**PHPUnit Example:**

```php
use PHPUnit\Framework\TestCase;
use Rcalicdan\Event\Event;

class OrderPlacementTest extends TestCase
{
    protected function setUp(): void
    {
        Event::reset();
    }
    
    public function test_placing_order_emits_event()
    {
        $captured = null;
        
        Event::on('order.placed', function ($order) use (&$captured) {
            $captured = $order;
        });
        
        $service = new OrderService($this->orders);
        $service->placeOrder($command);
        
        $this->assertInstanceOf(OrderDTO::class, $captured);
        $this->assertEquals('123', $captured->orderId);
    }
    
    public function test_order_placement_triggers_all_listeners()
    {
        $emailSent = false;
        $inventoryReserved = false;
        
        Event::on('order.placed', function () use (&$emailSent) {
            $emailSent = true;
        });
        
        Event::on('order.placed', function () use (&$inventoryReserved) {
            $inventoryReserved = true;
        });
        
        $service = new OrderService($this->orders);
        $service->placeOrder($command);
        
        $this->assertTrue($emailSent);
        $this->assertTrue($inventoryReserved);
    }
}
```

**Pest Example:**

```php
use Rcalicdan\Event\Event;

beforeEach(function () {
    Event::reset();
});

test('placing order emits event', function () {
    $captured = null;
    
    Event::on('order.placed', function ($order) use (&$captured) {
        $captured = $order;
    });
    
    $service = new OrderService($this->orders);
    $service->placeOrder($command);
    
    expect($captured)
        ->toBeInstanceOf(OrderDTO::class)
        ->orderId->toBe('123');
});

it('triggers all listeners when order is placed', function () {
    $emailSent = false;
    $inventoryReserved = false;
    
    Event::on('order.placed', function () use (&$emailSent) {
        $emailSent = true;
    });
    
    Event::on('order.placed', function () use (&$inventoryReserved) {
        $inventoryReserved = true;
    });
    
    $service = new OrderService($this->orders);
    $service->placeOrder($command);
    
    expect($emailSent)->toBeTrue()
        ->and($inventoryReserved)->toBeTrue();
});

test('event contains correct order data', function () {
    $capturedData = [];
    
    Event::on('order.placed', function ($order) use (&$capturedData) {
        $capturedData = [
            'orderId' => $order->orderId,
            'customerId' => $order->customerId,
            'total' => $order->total,
        ];
    });
    
    $service = new OrderService($this->orders);
    $service->placeOrder($command);
    
    expect($capturedData)
        ->toHaveKey('orderId')
        ->toHaveKey('customerId')
        ->toHaveKey('total')
        ->and($capturedData['orderId'])->not->toBeEmpty();
});
```

### Testing with Discovery

**PHPUnit Example:**

```php
use PHPUnit\Framework\TestCase;
use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

class UserRegistrationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        Event::reset();
        ListenerDiscovery::reset();
        
        // Discover test listeners
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
            failFast: true
        );
    }
    
    public function test_user_registration_flow()
    {
        $service = new UserRegistrationService(/* ... */);
        $user = $service->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
        
        // Assert user was created
        $this->assertNotNull($user->id);
        
        // Assert events were processed
        $this->assertTrue($this->emailWasSent($user->email));
        $this->assertTrue($this->profileWasCreated($user->id));
    }
}
```

**Pest Example:**

```php
use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
    
    // Discover test listeners
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        failFast: true
    );
});

test('user registration flow completes successfully', function () {
    $service = new UserRegistrationService(/* ... */);
    $user = $service->register([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    
    expect($user->id)->not->toBeNull()
        ->and($this->emailWasSent($user->email))->toBeTrue()
        ->and($this->profileWasCreated($user->id))->toBeTrue();
});

it('sends welcome email during registration', function () {
    $emailSent = false;
    
    Event::on('user.registered', function () use (&$emailSent) {
        $emailSent = true;
    });
    
    $service = new UserRegistrationService(/* ... */);
    $service->register([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    
    expect($emailSent)->toBeTrue();
});

it('creates user profile during registration', function () {
    $profileCreated = false;
    
    Event::on('user.registered', function () use (&$profileCreated) {
        $profileCreated = true;
    });
    
    $service = new UserRegistrationService(/* ... */);
    $service->register([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    
    expect($profileCreated)->toBeTrue();
});
```

### Testing Error Handling

**PHPUnit Example:**

```php
use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_listener_handles_api_exception_gracefully()
    {
        $api = $this->createMock(WarehouseApi::class);
        $logger = $this->createMock(LoggerInterface::class);
        $queue = $this->createMock(RetryQueue::class);
        
        $listener = new NotifyWarehouse($api, $logger, $queue);
        
        $order = new OrderDTO(
            orderId: '123',
            customerId: '456',
            total: 99.99,
            itemCount: 2,
            placedAt: new DateTimeImmutable(),
            ipAddress: '127.0.0.1'
        );
        
        $api->expects($this->once())
            ->method('sendOrder')
            ->willThrowException(new ApiException('Connection timeout'));
        
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to notify warehouse'),
                $this->arrayHasKey('order_id')
            );
        
        $queue->expects($this->once())
            ->method('add')
            ->with('warehouse.notify', $order);
        
        // Should not throw
        $listener->handle($order);
    }
}
```

**Pest Example:**

```php
test('listener handles api exception gracefully', function () {
    $api = Mockery::mock(WarehouseApi::class);
    $logger = Mockery::mock(LoggerInterface::class);
    $queue = Mockery::mock(RetryQueue::class);
    
    $listener = new NotifyWarehouse($api, $logger, $queue);
    
    $order = new OrderDTO(
        orderId: '123',
        customerId: '456',
        total: 99.99,
        itemCount: 2,
        placedAt: new DateTimeImmutable(),
        ipAddress: '127.0.0.1'
    );
    
    $api->shouldReceive('sendOrder')
        ->once()
        ->andThrow(new ApiException('Connection timeout'));
    
    $logger->shouldReceive('error')
        ->once()
        ->with('Failed to notify warehouse', Mockery::hasKey('order_id'));
    
    $queue->shouldReceive('add')
        ->once()
        ->with('warehouse.notify', $order);
    
    // Should not throw
    expect(fn() => $listener->handle($order))->not->toThrow(Exception::class);
});

it('retries failed warehouse notifications', function () {
    $api = Mockery::mock(WarehouseApi::class);
    $queue = Mockery::mock(RetryQueue::class);
    
    $listener = new NotifyWarehouse($api, Mockery::mock(LoggerInterface::class), $queue);
    
    $order = new OrderDTO(
        orderId: '123',
        customerId: '456',
        total: 99.99,
        itemCount: 2,
        placedAt: new DateTimeImmutable(),
        ipAddress: '127.0.0.1'
    );
    
    $api->shouldReceive('sendOrder')->andThrow(new ApiException());
    $queue->shouldReceive('add')->once();
    
    $listener->handle($order);
    
    expect($queue)->toHaveReceived('add');
});
```

### Testing with Datasets

**Pest Example with Datasets:**

```php
```php
use Rcalicdan\Event\Event;

test('event names follow naming conventions', function ($eventName, $isValid) {
    if ($isValid) {
        expect($eventName)
            ->toMatch('/^[a-z]+\.[a-z]+(\.[a-z]+)?$/');
    } else {
        expect($eventName)
            ->not->toMatch('/^[a-z]+\.[a-z]+(\.[a-z]+)?$/');
    }
})->with([
    ['user.registered', true],
    ['order.placed', true],
    ['payment.completed', true],
    ['user.password.reset', true],
    ['registerUser', false],
    ['user_registered', false],
    ['User.Registered', false],
]);

test('listeners handle different order statuses', function ($status, $expectedAction) {
    $actionTaken = null;
    
    Event::on('order.status.changed', function ($order) use (&$actionTaken) {
        $actionTaken = $order->status;
    });
    
    $order = new OrderDTO(
        orderId: '123',
        customerId: '456',
        total: 99.99,
        itemCount: 2,
        placedAt: new DateTimeImmutable(),
        ipAddress: '127.0.0.1',
        status: $status
    );
    
    Event::emit('order.status.changed', $order);
    
    expect($actionTaken)->toBe($expectedAction);
})->with([
    ['pending', 'pending'],
    ['processing', 'processing'],
    ['shipped', 'shipped'],
    ['delivered', 'delivered'],
    ['cancelled', 'cancelled'],
]);

test('event payload validation', function ($payload, $isValid) {
    if ($isValid) {
        expect(fn() => Event::emit('test.event', $payload))
            ->not->toThrow(Exception::class);
    } else {
        expect(fn() => Event::emit('test.event', $payload))
            ->toThrow(InvalidArgumentException::class);
    }
})->with([
    [new UserDTO('123', 'test@example.com', 'Test', new DateTimeImmutable()), true],
    [['valid' => 'array'], true],
    ['string', true],
    [123, true],
    [null, false],
]);
```

---

## Common Anti-Patterns

### 1. Using Events for Validation

```php
// BAD: Validation in event listener
#[ListenTo('user.register')]
class ValidateUser
{
    public function handle(array $data)
    {
        if (!$this->validator->validate($data)) {
            return false; // Stop propagation
        }
    }
}

// GOOD: Validate before emitting
if (!$this->validator->validate($data)) {
    throw new ValidationException();
}

$user = User::create($data);
Event::emit('user.registered', new UserDTO($user));
```

### 2. Event Chains

```php
// BAD: Event triggering another event (chain reaction)
#[ListenTo('order.placed')]
class ProcessOrder
{
    public function handle(OrderDTO $order)
    {
        $this->processPayment($order);
        Event::emit('payment.processed', $payment); // Chain!
    }
}

#[ListenTo('payment.processed')]
class ShipOrder
{
    public function handle(PaymentDTO $payment)
    {
        $this->shipOrder($payment->orderId);
        Event::emit('order.shipped', $order); // Chain!
    }
}

// GOOD: Explicit workflow
class OrderService
{
    public function placeOrder(Order $order)
    {
        $order->place();
        Event::emit('order.placed', new OrderDTO($order));
        
        $payment = $this->processPayment($order);
        Event::emit('payment.processed', new PaymentDTO($payment));
        
        $this->shipOrder($order);
        Event::emit('order.shipped', new OrderDTO($order));
    }
}
```

### 3. Expecting Return Values

```php
// BAD: Expecting data from listeners
$result = Event::emit('get.data', $id);
// Events don't return values!

// GOOD: Use a query or repository
$data = $this->repository->find($id);
```

### 4. Modifying Event Data

```php
// BAD: Listener modifying event data
#[ListenTo('order.placed')]
class AddDiscount
{
    public function handle(Order $order)
    {
        $order->applyDiscount(10); // Modifying mutable object
    }
}

// GOOD: Pass immutable DTOs
#[ListenTo('order.placed')]
class SendConfirmation
{
    public function handle(OrderDTO $order)
    {
        // Can only read, cannot modify
        $this->mailer->send($order->customerId);
    }
}
```

### 5. Tight Coupling

```php
// BAD: Listener knowing too much
#[ListenTo('order.placed')]
class OrderHandler
{
    public function handle(OrderDTO $order)
    {
        // Listener shouldn't orchestrate business logic
        $this->inventory->reserve($order);
        $this->payment->process($order);
        $this->shipping->schedule($order);
    }
}

// GOOD: Separate, focused listeners
#[ListenTo('order.placed')]
class ReserveInventory
{
    public function handle(OrderDTO $order)
    {
        $this->inventory->reserve($order);
    }
}

#[ListenTo('order.placed')]
class ProcessPayment
{
    public function handle(OrderDTO $order)
    {
        $this->payment->process($order);
    }
}
```

---

## Production Checklist

### Configuration

- [ ] Resilient mode enabled (`Event::resilient()`)
- [ ] Error event handler registered
- [ ] Max listeners set appropriately (`Event::setMaxListeners(100)`)
- [ ] Discovery caching enabled
- [ ] Cache refresh disabled in production

### Code Quality

- [ ] All event payloads if necessary use immutable DTOs (readonly classes)
- [ ] Event names follow naming conventions
- [ ] Listeners have single responsibility
- [ ] Listeners are idempotent
- [ ] Error handling in all listeners

### Performance

- [ ] Expensive operations queued for async processing
- [ ] Wildcard listeners minimized
- [ ] hasListeners() checks before expensive operations
- [ ] Priorities used appropriately

### Monitoring

- [ ] Error events logged
- [ ] Error events sent to tracking service (Sentry, etc.)
- [ ] Event metrics collected
- [ ] Memory usage monitored for long-running processes

### Testing

- [ ] Unit tests for all listeners
- [ ] Integration tests for event flows
- [ ] Error scenarios tested
- [ ] Performance tested under load

---

[← Back to Main Documentation](../README.md) | [Previous: API Reference](api-reference.md) | [Next: Examples →](examples.md)
