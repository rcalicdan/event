# Examples

Real-world examples demonstrating how to use the Event library in different scenarios.

## Table of Contents

- [Framework Integration](#framework-integration)
- [Usage Patterns](#usage-patterns)
- [Advanced Patterns](#advanced-patterns)

---

## Framework Integration

### Laravel

Laravel service providers have two key methods: `register()` for binding services into the container (called first for all providers), and `boot()` for initialization tasks after all providers are registered.

**Create Service Provider**
```php
php artisan make:provider EventServiceProvider
```

**Service Provider** (`app/Providers/EventServiceProvider.php`)
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services (bindings only).
     */
    public function register(): void
    {
        // Register any service container bindings here if needed
    }

    /**
     * Bootstrap services (after all providers registered).
     */
    public function boot(): void
    {
        // Configure error handling
        Event::resilient(); // Production mode
        Event::setMaxListeners(100);

        // Discover all listeners with Laravel's container for DI
        ListenerDiscovery::discover(
            directory: [
                app_path('Listeners'),
                app_path('Subscribers'),
            ],
            failFast: config('app.debug'),
            cachePath: storage_path('framework/cache/events'),
            refreshCache: config('app.debug'),
            container: $this->app // Laravel's PSR-11 container
        );
    }
}
```

**Register Provider** (`bootstrap/providers.php` for Laravel 11+)
```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
];
```

**Listener with Dependency Injection**
```php
<?php

namespace App\Listeners;

use App\DTOs\UserDTO;
use App\Enums\UserEvents;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;
use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(UserEvents::Registered->value, priority: 10)]
class SendWelcomeEmail
{
    public function handle(UserDTO $user): void
    {
        Mail::to($user->email)->send(new WelcomeMail($user));
    }
}
```

**Emit Events**
```php
<?php

namespace App\Services;

use App\DTOs\UserDTO;
use App\Enums\UserEvents;
use App\Models\User;
use Rcalicdan\Event\Event;

class UserService
{
    public function register(array $data): User
    {
        $user = User::create($data);

        Event::emit(UserEvents::Registered, new UserDTO(
            id: $user->id,
            email: $user->email,
            name: $user->name,
            registeredAt: $user->created_at
        ));

        return $user;
    }
}
```

---

### Symfony

Symfony 3.3+ provides a `build()` method in the Kernel class for registering compiler passes and manipulating the container during the building process.

**Kernel Configuration** (`src/Kernel.php`)
```php
<?php

namespace App;

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Called during container building process.
     */
    protected function build(ContainerBuilder $container): void
    {
        // Register compiler passes if needed
    }

    /**
     * Called after the container is built and before handling requests.
     */
    public function boot(): void
    {
        parent::boot();

        // Configure event system
        Event::resilient();
        Event::setMaxListeners(100);

        // Discover listeners with Symfony's container
        ListenerDiscovery::discover(
            directory: $this->getProjectDir() . '/src/EventListener',
            failFast: $this->isDebug(),
            cachePath: $this->getCacheDir() . '/event_listeners',
            refreshCache: $this->isDebug(),
            container: $this->container // Symfony's PSR-11 container
        );
    }
}
```

**Alternative: Bundle Integration**

Create a custom bundle for reusable event setup:

```php
<?php

namespace App\EventBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AppEventBundle extends Bundle
{
    /**
     * Called when the bundle is booted.
     */
    public function boot(): void
    {
        // Event system initialization
        Event::resilient();
        
        ListenerDiscovery::discover(
            directory: $this->getPath() . '/../EventListener',
            container: $this->container
        );
    }
}
```

**Listener with Dependency Injection**
```php
<?php

namespace App\EventListener;

use App\DTO\UserDTO;
use App\Enum\UserEvents;
use App\Service\NotificationService;
use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(UserEvents::Registered->value, priority: 5)]
readonly class NotifyAdminOnUserRegistration
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(UserDTO $user): void
    {
        $this->notificationService->notifyAdmin(
            "New user registered: {$user->email}"
        );
    }
}
```

---

### Slim

Slim 4 uses a bootstrap file that creates the application instance, registers middleware, and loads routes.

**Bootstrap File** (`config/bootstrap.php`)
```php
<?php

use DI\Container;
use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Configure Event System
Event::resilient();
Event::setMaxListeners(100);

// Discover event listeners
ListenerDiscovery::discover(
    directory: __DIR__ . '/../src/Listeners',
    cachePath: __DIR__ . '/../var/cache/events',
    refreshCache: $_ENV['APP_ENV'] === 'development',
    container: $container
);

// Add Middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Load routes
(require __DIR__ . '/routes.php')($app);

return $app;
```

**Front Controller** (`public/index.php`)
```php
<?php

/** @var \Slim\App $app */
$app = require __DIR__ . '/../config/bootstrap.php';

$app->run();
```

**Routes File** (`config/routes.php`)
```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello World!');
        return $response;
    });

    $app->group('/api', function (RouteCollectorProxy $group) {
        $group->post('/users', 'App\Controllers\UserController:create');
        $group->get('/users/{id}', 'App\Controllers\UserController:show');
    });
};
```

**Listener**
```php
<?php

namespace App\Listeners;

use App\DTO\UserDTO;
use App\Enum\UserEvents;
use Psr\Log\LoggerInterface;
use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(UserEvents::LoggedIn->value)]
#[ListenTo(UserEvents::LoggedOut->value)]
readonly class LogUserActivity
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function handle(UserDTO $user): void
    {
        $this->logger->info("User activity: {$user->email}");
    }
}
```

---

## Usage Patterns

### Event Singleton (Global Facade)

**Define Events**
```php
readonly class OrderDTO
{
    public function __construct(
        public string $orderId,
        public float $total,
        public \DateTimeImmutable $placedAt
    ) {}
}

enum OrderEvents: string
{
    case Placed = 'order.placed';
    case Shipped = 'order.shipped';
}
```

**Register & Emit**
```php
Event::on(OrderEvents::Placed, function (OrderDTO $order) {
    echo "Processing order {$order->orderId}\n";
}, priority: 10);

Event::emit(OrderEvents::Placed, new OrderDTO(
    orderId: 'ORD-12345',
    total: 299.99,
    placedAt: new \DateTimeImmutable()
));
```

---

### EventEmitter Class (Module Isolation)

```php
class PaymentModule
{
    private EventEmitter $events;

    public function __construct()
    {
        $this->events = new EventEmitter();
        
        $this->events->on(PaymentEvents::Received, function (PaymentDTO $payment) {
            echo "Payment received: {$payment->amount}\n";
        });
    }

    public function processPayment(float $amount): void
    {
        $this->events->emit(PaymentEvents::Received, new PaymentDTO(
            paymentId: uniqid('PAY-'),
            amount: $amount,
            currency: 'USD',
            status: 'completed'
        ));
    }
}
```

---

### EventEmitterTrait (Domain Objects)

```php
class Warehouse
{
    use EventEmitterTrait;
    
    private array $inventory = [];

    public function __construct()
    {
        $this->on(InventoryEvents::LowStock, function (InventoryDTO $item) {
            echo "LOW STOCK: {$item->sku}\n";
        });
    }

    public function reserve(string $sku, int $qty): void
    {
        $this->inventory[$sku] -= $qty;
        
        $this->emit(InventoryEvents::Reserved, new InventoryDTO(
            sku: $sku,
            quantity: $qty,
            location: 'Warehouse-A'
        ));
        
        if ($this->inventory[$sku] < 10) {
            $this->emit(InventoryEvents::LowStock, new InventoryDTO(
                sku: $sku,
                quantity: $this->inventory[$sku],
                location: 'Warehouse-A'
            ));
        }
    }
}
```

---

### Listener Discovery (Recommended)

**Listeners**
```php
#[ListenTo(UserEvents::Registered->value, priority: 10)]
class SendWelcomeEmail
{
    public function handle(UserDTO $user): void
    {
        mail($user->email, 'Welcome!', "Hello {$user->name}!");
    }
}

#[ListenTo(UserEvents::Registered->value)]
#[ListenTo(UserEvents::EmailVerified->value)]
class NotifyAdmins
{
    public function handle(UserDTO $user): void
    {
        error_log("Admin notification: {$user->email}");
    }
}

#[ListenTo(UserEvents::Registered->value)]
function logUserRegistration(UserDTO $user): void
{
    error_log("New user: {$user->email}");
}
```

**Bootstrap**
```php
ListenerDiscovery::discover(
    directory: __DIR__ . '/src/Listeners',
    cachePath: __DIR__ . '/var/cache',
    refreshCache: true
);

Event::emit(UserEvents::Registered, new UserDTO(
    id: '123',
    email: 'user@example.com',
    name: 'John Doe',
    registeredAt: new \DateTimeImmutable()
));
```

---

### Context-Bound Events

**WebSocket Connection**
```php
class WebSocketConnection
{
    use EventEmitterTrait;
    
    private string $connectionId;

    public function __construct(string $clientIp)
    {
        $this->connectionId = uniqid('conn-');
        
        $this->on(WebSocketEvents::MessageReceived, function (MessageDTO $msg) {
            echo "[{$this->connectionId}] Message: {$msg->content}\n";
        });
    }

    public function receive(string $message): void
    {
        $this->emit(WebSocketEvents::MessageReceived, new MessageDTO(
            messageId: uniqid('msg-'),
            from: 'client',
            content: $message,
            sentAt: new \DateTimeImmutable()
        ));
    }
}

// Each connection has isolated events
$conn1 = new WebSocketConnection('192.168.1.100');
$conn2 = new WebSocketConnection('192.168.1.101');

$conn1->on(WebSocketEvents::MessageReceived, fn($msg) => processMessage($msg));
$conn2->on(WebSocketEvents::MessageReceived, fn($msg) => storeMessage($msg));
```

**Cache Manager**
```php
class CacheManager
{
    private EventEmitter $events;
    private array $cache = [];

    public function __construct()
    {
        $this->events = new EventEmitter();
        
        $this->events->on(CacheEvents::Miss, function (CacheDTO $data) {
            echo "Cache miss: {$data->key}\n";
        });
    }

    public function get(string $key): mixed
    {
        if (isset($this->cache[$key])) {
            $this->events->emit(CacheEvents::Hit, new CacheDTO($key, $this->cache[$key], 0, new \DateTimeImmutable()));
            return $this->cache[$key];
        }
        
        $this->events->emit(CacheEvents::Miss, new CacheDTO($key, null, 0, new \DateTimeImmutable()));
        return null;
    }

    public function getEmitter(): EventEmitter
    {
        return $this->events;
    }
}
```

---

### Plugin System

```php
interface PluginInterface
{
    public function register(EventEmitter $events): void;
    public function unregister(EventEmitter $events): void;
}

class AuthPlugin implements PluginInterface
{
    public function register(EventEmitter $events): void
    {
        $events->on(PluginEvents::BeforeRequest, function (RequestDTO $req) {
            if (!isset($req->data['token'])) {
                return false; // Stop propagation
            }
        }, priority: 100);
    }

    public function unregister(EventEmitter $events): void
    {
        $events->removeAllListeners(PluginEvents::BeforeRequest);
    }
}

class PluginManager
{
    private EventEmitter $events;

    public function __construct()
    {
        $this->events = new EventEmitter();
    }

    public function loadPlugin(PluginInterface $plugin): void
    {
        $plugin->register($this->events);
    }

    public function handleRequest(string $method, string $path, array $data = []): void
    {
        $request = new RequestDTO($method, $path, $data, new \DateTimeImmutable());
        $this->events->emit(PluginEvents::BeforeRequest, $request);
    }
}
```

---

## Advanced Patterns

### Choreography Saga

```php
enum OrderSagaEvents: string
{
    case OrderPlaced = 'saga.order.placed';
    case PaymentProcessed = 'saga.payment.processed';
    case InventoryReserved = 'saga.inventory.reserved';
}

#[ListenTo(OrderSagaEvents::OrderPlaced->value)]
class ProcessPayment
{
    public function handle(OrderDTO $order): void
    {
        Event::emit(OrderSagaEvents::PaymentProcessed, new PaymentDTO(...));
    }
}

#[ListenTo(OrderSagaEvents::PaymentProcessed->value)]
class ReserveInventory
{
    public function handle(PaymentDTO $payment): void
    {
        Event::emit(OrderSagaEvents::InventoryReserved, new InventoryDTO(...));
    }
}
```

---

### Async Processing (ReactPHP)

```php
use React\EventLoop\Loop;
use function React\Async\async;

#[ListenTo(AsyncEvents::EmailQueued->value)]
class SendEmailAsync
{
    public function handle(EmailDTO $email): void
    {
        async(function () use ($email) {
            // Non-blocking email sending
            await(sendEmail($email));
        })();
    }
}

ListenerDiscovery::discover(__DIR__ . '/src/AsyncListeners');
Event::emit(AsyncEvents::EmailQueued, new EmailDTO(...));
```

---

### Message Queue Integration

```php
class RedisQueueEmitter implements EventEmitterInterface
{
    private Predis\Client $redis;

    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        $payload = json_encode(['event' => $event, 'data' => serialize($args)]);
        $this->redis->rpush("events:{$event}", [$payload]);
    }

    public function consume(string|\BackedEnum $event): void
    {
        while (true) {
            [$, $payload] = $this->redis->blpop(["events:{$event}"], 0);
            $data = json_decode($payload, true);
            
            foreach ($this->listeners[$event] ?? [] as $listener) {
                $listener['callback'](...unserialize($data['data']));
            }
        }
    }
}

// Producer
$emitter = new RedisQueueEmitter();
Event::setInstance($emitter);
Event::emit(QueueEvents::OrderPlaced, new OrderDTO(...));

// Consumer (separate process)
$emitter->consume(QueueEvents::OrderPlaced);
```

---

## Summary

**Choose Your Pattern:**
- **Attribute Discovery**: Most applications (recommended)
- **Event Singleton**: Simple apps, global events
- **EventEmitter Class**: Module isolation
- **EventEmitterTrait**: Domain objects
- **Context-Bound**: Plugins, multi-tenant, state machines
- **Async**:Async Libraries like ReactPHP for non-blocking
- **Message Queues**: Distributed systems, guaranteed delivery

---

[← Back to Main Documentation](../README.md) | [Previous: Best Practices](best-practices.md) |