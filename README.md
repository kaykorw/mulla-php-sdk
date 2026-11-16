# Mulla PHP SDK

Official PHP library for the Mulla payment gateway. Accept **MTN Mobile Money** and **Airtel Money** payments in Rwanda, send payouts, and handle webhooks in a few lines of PHP.

- PHP 8.1+, no required dependencies (uses ext-curl; plug in Guzzle or any PSR-18 client if you prefer)
- Typed models and enums, so your IDE autocompletes everything
- Idempotency keys, retries and required headers handled for you
- Webhook signature verification and an event dispatcher
- Test helpers to fake the API in your own test suite

## Installation

```bash
composer require mulla/mulla-php
```

## Quick start

```php
use Mulla\MullaClient;
use Mulla\Enums\Provider;

$mulla = new MullaClient('sk_test_...'); // test keys use the sandbox: https://mfs.kayko.dev/gateway

$charge = $mulla->charges->initiate([
    'provider'     => Provider::MTN_RW,
    'amount'       => 5000,
    'phone_number' => '250788000000',
    'reason'       => 'Order #1001',
    'metadata'     => ['order_id' => 1001],
]);

// The customer approves the prompt on their device.
// Mulla then sends you a webhook, or you can poll:
$charge = $mulla->charges->waitForFinalStatus($charge->id);

if ($charge->isSuccessful()) {
    // fulfil the order
}
```

Test keys (`sk_test_...`) only work on the sandbox server and live keys (`sk_live_...`) only on production.

| Key | Default gateway |
|---|---|
| `sk_test_...` | `https://mfs.kayko.dev/gateway` (sandbox) |
| `sk_live_...` | `https://core.mulla.app/gateway` (production) |

Override with the `baseUrl` option or the `MULLA_BASE_URL` environment variable (e.g. a local server).

You can paste the gateway URL exactly as the dashboard shows it (`https://host/gateway`); the SDK adds `/v1`.

## Important Usage: Good to know

### Builders or arrays: your choice

Every method accepts a plain array (API field names) or a fluent builder with autocompletion and required-field checks:

```php
use Mulla\Params\InitiateChargeParams;

$charge = $mulla->charges->initiate(
    InitiateChargeParams::make()
        ->provider(Provider::AIRTEL_RW)
        ->phoneNumber('250733000000')
        ->amount(1000)
        ->metadata(['order_id' => 1001])
);
```

### Hosted checkout

Let Mulla's page collect the provider and phone number:

```php
$session = $mulla->charges->createCheckout([
    'amount'      => 25000,
    'reason'      => 'Order #1001',
    'metadata'    => ['order_id' => 1001],
    'success_url' => 'https://shop.example/thanks',
    'cancel_url'  => 'https://shop.example/cart',
]);

header('Location: ' . $session->checkoutUrl);
```

> Return URLs are not signed. Always confirm payment with a webhook or `$mulla->charges->retrieve($session->charge)`.

### Listing and pagination

`foreach` walks through **every page** automatically:

```php
use Mulla\Enums\ChargeStatus;

foreach ($mulla->charges->list(['status' => ChargeStatus::SUCCESSFUL]) as $charge) {
    echo $charge->reference, ' ', $charge->amount, PHP_EOL;
}

$page = $mulla->charges->list(['limit' => 20]); // one page: $page->data, $page->total, $page->nextPage()
```

### Available services

| Service | Methods |
|---|---|
| `$mulla->charges` | `initiate`, `createCheckout`, `retrieve`, `list`, `waitForFinalStatus` |
| `$mulla->checkout` | `retrieve`, `pay`, `eventsUrl` (public, token based) |
| `$mulla->payouts` | `initiate`, `retrieve`, `list`, `waitForFinalStatus` |
| `$mulla->recharges` | `initiate`, `retrieve`, `list`, `waitForFinalStatus` |
| `$mulla->invoices` | `create`, `retrieve`, `list`, `update`, `updateStatus`, `listPayments`, `retrievePayment`, `retrievePublic`, `pay` |
| `$mulla->customers` | `create`, `retrieve`, `list`, `update`, `blacklist`, `whitelist` |
| `$mulla->webhookEndpoint` | `create`, `retrieve`, `update`, `rotateSecret`, `test` |
| `$mulla->referenceData` | `banks`, `bankBranches`, `countries`, `currencies`, `scopes` |

Each service needs the matching scope on your API key (CHARGES, PAYOUTS, INVOICES, RECHARGES, CUSTOMERS, WEBHOOKS).

## Webhooks

```php
use Mulla\Enums\WebhookEventType;
use Mulla\Exceptions\SignatureVerificationException;
use Mulla\Webhooks\{Event, EventDispatcher, Webhook};

$payload = file_get_contents('php://input'); // the RAW body

try {
    $event = Webhook::constructEvent($payload, Webhook::headersFromGlobals(), getenv('MULLA_WEBHOOK_SECRET'));
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

(new EventDispatcher())
    ->on(WebhookEventType::CHARGE_SUCCESS, fn (Event $e) => markPaid($e->objectId(), $e->reference()))
    ->on(WebhookEventType::CHARGE_FAILED, fn (Event $e) => markFailed($e->objectId()))
    ->dispatch($event);

http_response_code(200);
```

Tips:
- Mulla retries failed deliveries, so use `$event->id` to skip events you already handled.
- Framework users: pass the request's headers array (e.g. `$request->headers->all()` in Symfony or Laravel) and the raw body (`$request->getContent()`).
- Set up your endpoint with `$mulla->webhookEndpoint->create('https://...')` and store the returned `secret`.

## Error handling

Every exception extends `Mulla\Exceptions\MullaException`:

| Exception | When |
|---|---|
| `InvalidRequestException` | 400: invalid parameters, minimum amount, insufficient balance |
| `AuthenticationException` | 401: invalid, expired or revoked key |
| `PermissionException` | 403: missing scope, IP not allowed, test/live key mismatch, merchant not active |
| `NotFoundException` | 404 |
| `ConflictException` / `IdempotencyException` | 409: payment in progress / idempotency key already used |
| `RateLimitException` | 429: see `getRetryAfter()` |
| `ApiServerException` | 5xx |
| `ConnectionException` | network problem, no response |
| `SignatureVerificationException` | invalid webhook signature |
| `InvalidArgumentException` | bad SDK usage, raised before any request |

```php
try {
    $mulla->payouts->initiate([...]);
} catch (InvalidRequestException $e) {
    $e->getErrorCode();  // e.g. "INSUFFICIENT_BALANCE"
    $e->getStatusCode(); // 400
    $e->getBody();       // full decoded error body
}
```

## Configuration

```php
$mulla = new MullaClient('sk_live_...', [
    'baseUrl'        => 'https://core.mulla.app/gateway', // optional: defaults from the key
    'timeout'        => 30,          // seconds
    'connectTimeout' => 10,
    'maxRetries'     => 2,           // network errors, 429 and 5xx; 0 disables
    'lang'           => 'fr',        // en, fr, rw, sw: language of API messages
    'appInfo'        => 'my-shop/1.4', // appended to the User-Agent
    'logger'         => $psr3Logger, // any PSR-3 logger or callable; keys are never logged
    'transport'      => $customTransport, // e.g. Psr18Transport for Guzzle
    'middleware'     => [$myTracingMiddleware],
]);
```

Per-request options go in the last argument of every method:

```php
$mulla->charges->initiate($params, [
    'idempotency_key' => $myUuid, // make your own retries safe
    'lang'            => 'rw',
    'timeout'         => 60,
]);
```

### Using Guzzle or another PSR-18 client

```php
use Mulla\Http\Transport\Psr18Transport;

$mulla = new MullaClient('sk_test_...', [
    'transport' => new Psr18Transport(new \GuzzleHttp\Client(['timeout' => 30]), new \GuzzleHttp\Psr7\HttpFactory()),
]);
```

## Testing your integration

The SDK ships helpers so your tests never call the real API:

```php
use Mulla\MullaClient;
use Mulla\Testing\{FakeTransport, WebhookSigner};
use Mulla\Enums\WebhookEventType;

$fake = new FakeTransport();
$fake->pushJson(['id' => 'chg_1', 'reference' => 'CHG1', 'status' => 'PENDING', 'amount' => 5000]);

$mulla = new MullaClient('sk_test_abc_xyz', ['baseUrl' => 'https://mulla.test/gateway/v1', 'transport' => $fake]);
// ... run your code ...
$fake->assertSent(fn ($request) => str_ends_with($request->url, '/charges/initiate'));

// Signed webhook requests for your webhook handler tests:
['body' => $body, 'headers' => $headers] = WebhookSigner::event(
    WebhookEventType::CHARGE_SUCCESS,
    ['charge' => 'chg_1', 'reference' => 'CHG1', 'amount' => 5000, 'currency' => 'RWF', 'status' => 'SUCCESSFUL'],
    'my-webhook-secret',
);
```

## Development

```bash
composer install
composer test               # unit tests
composer analyse            # PHPStan level 8
composer cs                 # code style (php-cs-fixer --dry-run)

# Against a real server (local docker stack or sandbox):
MULLA_SECRET_KEY=sk_test_... MULLA_BASE_URL=http://localhost:5605/gateway/v1 composer test:integration
```

## License

MIT
