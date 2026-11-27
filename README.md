# Mulla PHP SDK

<!-- x-release-please-start-version -->

> This guide applies to `kayko/mulla-php` **0.1.0** and the Mulla API **v1**. See [Versioning and compatibility](#versioning-and-compatibility).

<!-- x-release-please-end -->

## Introduction

The official PHP library for Mulla, the mobile money payment gateway for Rwanda.

With it you can:

- take payments from **MTN Mobile Money** and **Airtel Money** wallets;
- send money to a wallet (payouts);
- send invoices and track their payments;
- receive and verify webhooks (payment notifications).

Why use it instead of calling the API yourself:

- **Typed.** Models, enums and optional builders, so your IDE autocompletes every field and status.
- **Safe by default.** It adds the required headers, retries only when no payment can be duplicated, and never logs your key.
- **No required dependencies.** It uses ext-curl. You can plug in Guzzle or any PSR-18 client instead.
- **Test helpers** to fake the API in your own test suite.

Using Laravel? Use [`kayko/mulla-laravel`](https://github.com/kaykorw/mulla-laravel-sdk). It builds on this SDK and adds config, a facade and a webhook route.

## Table of contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How Mulla payments work](#how-mulla-payments-work)
- [Guides](#guides)
  - [Charge a phone number](#charge-a-phone-number)
  - [Use the hosted checkout page](#use-the-hosted-checkout-page)
  - [Handle webhooks](#handle-webhooks)
  - [Send a payout](#send-a-payout)
  - [Top up your balance](#top-up-your-balance)
  - [Invoice a customer](#invoice-a-customer)
  - [List and filter records](#list-and-filter-records)
  - [Handle errors](#handle-errors)
  - [Avoid duplicate payments](#avoid-duplicate-payments)
  - [Test your integration](#test-your-integration)
  - [Go live](#go-live)
- [Reference](#reference)
  - [Services and methods](#services-and-methods)
  - [Client options](#client-options)
  - [Per-request options](#per-request-options)
  - [Environment variables](#environment-variables)
  - [Statuses](#statuses)
  - [Webhook events](#webhook-events)
  - [Exceptions](#exceptions)
  - [Retry rules](#retry-rules)
  - [Response objects](#response-objects)
  - [Using Guzzle or another PSR-18 client](#using-guzzle-or-another-psr-18-client)
  - [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Versioning and compatibility](#versioning-and-compatibility)
- [Support](#support)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- **PHP 8.1 or newer** with the `curl` and `json` extensions.
- **A Mulla merchant account** and a secret API key from the Mulla dashboard.
- **The right scopes on the key.** A scope is a permission. Each service needs its own: `CHARGES`, `PAYOUTS`, `INVOICES`, `RECHARGES` or `CUSTOMERS`.

> **Server-side only.** Your secret key can move money. Keep it on your server and out of your code repository.

## Installation

```bash
composer require kayko/mulla-php
```

## Quick start

This uses the sandbox, so no real money moves.

**1. Add your test key** to your environment, for example in a `.env` file that you don't commit:

```bash
MULLA_SECRET_KEY=sk_test_...
```

**2. Create a client** with that key:

```php
use Mulla\MullaClient;
use Mulla\Enums\Provider;

$mulla = new MullaClient(['api_key' => getenv('MULLA_SECRET_KEY')]);
```

**3. Ask a customer to pay.** Mulla sends a payment prompt to their phone:

```php
$charge = $mulla->charges->initiate([
    'provider'     => Provider::MTN_RW,
    'amount'       => 5000, // RWF
    'phone_number' => '+250788000000',
    'reason'       => 'Order #1001',
]);

echo $charge->id, ' ', $charge->status->value; // "PENDING" until the customer answers
```

**4. Get the result.** For a quick test, wait for it:

```php
$charge = $mulla->charges->waitForFinalStatus($charge->id);

if ($charge->isSuccessful()) {
    // Mark the order as paid.
}
```

In production, use [webhooks](#handle-webhooks) instead of waiting.

## How Mulla payments work

Read this once. It explains the ideas the guides rely on.

### Payments finish later, not instantly

The customer has to approve a mobile money payment on their phone. So every charge starts as `PENDING` and changes later:

```text
PENDING ──► SUCCESSFUL   the customer approved
        ├─► FAILED       declined, wrong PIN, not enough money…
        └─► EXPIRED      the customer did not answer in time
```

Payouts, recharges and invoices work the same way, with their own [statuses](#statuses).

### Two ways to collect a payment

| Approach                                         | You collect                               | Best for                                       |
| ------------------------------------------------ | ----------------------------------------- | ---------------------------------------------- |
| [Direct charge](#charge-a-phone-number)          | The network and phone number, in your app | Apps with their own checkout form, USSD, POS   |
| [Hosted checkout](#use-the-hosted-checkout-page) | Nothing: Mulla's page asks the customer   | Websites that want the fastest, simplest setup |

### Two ways to learn the result

- **Webhooks (recommended).** Mulla calls a URL on your server when the status changes. See [Handle webhooks](#handle-webhooks).
- **Polling.** Your code asks Mulla repeatedly with `waitForFinalStatus()`. Fine for scripts and tests, but it blocks the PHP process while it waits.

### Test mode and live mode

The key prefix decides where requests go. You don't need to configure a URL.

| Key prefix | Mode                   | Server                           |
| ---------- | ---------------------- | -------------------------------- |
| `sk_test_` | Sandbox, no real money | `https://mfs.kayko.dev/gateway`  |
| `sk_live_` | Production, real money | `https://core.mulla.app/gateway` |

To use another server (for example a local one), set the `base_url` option. You can paste the URL exactly as the dashboard shows it; the SDK adds `/v1`.

### Amounts, phone numbers and field names

- **Amounts** are whole Rwandan francs (RWF), not cents. The minimum is 10.
- **Phone numbers** must use the international format with `+`, for example `+250788000000`. Other formats (`250788000000`, `0788000000`) are rejected with `VALIDATION_FAILED`.
- **Arrays use the API's field names** in snake_case (`phone_number`, `success_url`). The API rejects unknown fields, so a typo fails with an `InvalidRequestException`.
- **Builders are optional.** Each params class (`InitiateChargeParams`, `CreatePayoutParams`…) gives you autocompletion and checks required fields before sending:

```php
use Mulla\Params\InitiateChargeParams;

$charge = $mulla->charges->initiate(
    InitiateChargeParams::make()
        ->provider(Provider::AIRTEL_RW)
        ->phoneNumber('+250730000000')
        ->amount(1000)
        ->metadata(['order_id' => 1001])
);
```

### Key terms

| Term                | Meaning                                                                                                 |
| ------------------- | ------------------------------------------------------------------------------------------------------- |
| **Provider**        | The mobile money network: `MTN_RW` (MTN Mobile Money) or `AIRTEL_RW` (Airtel Money).                    |
| **Scope**           | A permission on your API key, such as `CHARGES`. Calls without it fail with `PermissionException`.      |
| **Webhook secret**  | A value Mulla gives you when you register your webhook URL. It proves a webhook really came from Mulla. |
| **Idempotency key** | A unique ID (UUID) sent with a request, so that sending it twice never creates two payments.            |

## Guides

Each guide is a short, complete task. They all start from a client:

```php
use Mulla\MullaClient;
use Mulla\Enums\Provider;

$mulla = new MullaClient(['api_key' => getenv('MULLA_SECRET_KEY')]);
```

### Charge a phone number

Use this when your app already knows the customer's network and phone number.

```php
$charge = $mulla->charges->initiate([
    'provider'     => Provider::AIRTEL_RW,
    'amount'       => 12000,
    'phone_number' => '+250730000000',
    'reason'       => 'Order #1001',          // shown to the customer
    'metadata'     => ['order_id' => 1001],   // your own data, kept with the charge
]);

$order->update(['mulla_charge_id' => $charge->id]);
```

Save `$charge->id` with your order so you can match them later. Then wait for the `charge.success` or `charge.failed` [webhook](#handle-webhooks).

To check a charge yourself at any time:

```php
$latest = $mulla->charges->retrieve($charge->id);
$latest->status; // ChargeStatus::PENDING, SUCCESSFUL, FAILED or EXPIRED
```

### Use the hosted checkout page

Use this on a website when you don't want to build a payment form. Mulla's page asks for the network and phone number.

**1. Create a session and send the customer to it:**

```php
$session = $mulla->charges->createCheckout([
    'amount'      => 25000,
    'reason'      => 'Order #1001',
    'metadata'    => ['order_id' => 1001],
    'success_url' => 'https://shop.example/orders/1001/thanks',
    'cancel_url'  => 'https://shop.example/cart',
]);

$order->update(['mulla_charge_id' => $session->charge]);
header('Location: ' . $session->checkoutUrl);
exit;
```

**2. Confirm the payment** with the webhook, or by retrieving the charge when the customer comes back:

```php
$charge = $mulla->charges->retrieve($order->mulla_charge_id);

if ($charge->isSuccessful()) {
    // Mark the order as paid.
}
```

> **Don't trust the return URL alone.** Anyone can open `success_url` in a browser. Always confirm with a webhook or `retrieve()`.

### Handle webhooks

A webhook is a POST request that Mulla sends to your server when something changes, such as a successful payment.

**1. Register your URL once** and save the secret it returns. Mulla shows the full secret only once:

```php
$endpoint = $mulla->webhookEndpoint->create('https://shop.example/mulla/webhook');
// Save $endpoint->secret as MULLA_WEBHOOK_SECRET.

$mulla->webhookEndpoint->test(); // Mulla sends a "webhook.test" event to your URL
```

**2. Write the endpoint.** Verify the signature on the **raw request body**, skip events you've already handled, then run your code:

```php
use Mulla\Enums\WebhookEventType;
use Mulla\Exceptions\SignatureVerificationException;
use Mulla\Webhooks\{Event, EventDispatcher, Webhook};

$payload = file_get_contents('php://input'); // the raw body, before any decoding

try {
    $event = Webhook::constructEvent($payload, Webhook::headersFromGlobals(), getenv('MULLA_WEBHOOK_SECRET'));
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

// Mulla may deliver the same event more than once.
if ($db->webhookEventExists($event->id)) {
    http_response_code(200);
    exit;
}

try {
    (new EventDispatcher())
        ->on(WebhookEventType::CHARGE_SUCCESS, fn (Event $e) => markPaidByChargeId($e->objectId()))
        ->on(WebhookEventType::CHARGE_FAILED, fn (Event $e) => markFailedByChargeId($e->objectId()))
        ->dispatch($event);

    $db->recordWebhookEvent($event->id);
    http_response_code(200);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500); // Mulla retries later
}
```

Reply with these status codes:

| Reply | When                                       | What Mulla does next |
| ----- | ------------------------------------------ | -------------------- |
| `200` | You handled the event, or it's a duplicate | Nothing              |
| `400` | The signature is missing, wrong or too old | Nothing              |
| `500` | Your code failed                           | Retries later        |

**With a framework** (Symfony, Slim…), pass the request's raw body and headers instead of the globals:

```php
$event = Webhook::constructEvent($request->getContent(), $request->headers->all(), $secret);
```

**Good to know:**

- **Find your record with `$event->objectId()`.** It returns the charge, payout or invoice ID. `$event->metadata()` returns `null` when the payload has no metadata, so don't rely on it alone.
- **Reply fast.** Record the event, then do slow work (emails, PDFs) in a queue.
- **Exclude the route from CSRF protection**, since Mulla can't send your CSRF token.
- **Old webhooks are rejected.** A signed webhook more than 5 minutes old fails, which stops replayed requests. Keep your server clock correct.
- **Rotating the secret:** `$mulla->webhookEndpoint->rotateSecret()` returns a new secret. Update `MULLA_WEBHOOK_SECRET` right away.

### Send a payout

A payout sends money from your Mulla balance to a mobile money wallet. You need the `PAYOUTS` scope and enough balance.

```php
$payout = $mulla->payouts->initiate([
    'provider'     => Provider::MTN_RW,
    'amount'       => 15000,
    'phone_number' => '+250788000000',
    'reason'       => 'Refund for order #1001', // required
]);
```

Listen for the `payout.success` and `payout.failed` webhooks, or check with `$mulla->payouts->retrieve($payout->id)`. A payout has succeeded when its status is `COMPLETED` (`$payout->isSuccessful()`).

If your balance is too low, you get an `InvalidRequestException` whose `getErrorCode()` is `INSUFFICIENT_BALANCE`.

### Top up your balance

A recharge moves money from a mobile money wallet into your Mulla balance, for example to fund payouts. You need the `RECHARGES` scope.

```php
$recharge = $mulla->recharges->initiate([
    'provider'     => Provider::MTN_RW,
    'amount'       => 100000,
    'phone_number' => '+250788000000', // the wallet that pays
]);

$recharge = $mulla->recharges->waitForFinalStatus($recharge->id);
```

### Invoice a customer

An invoice lets a customer pay a bill with one or more items. You need the `CUSTOMERS` and `INVOICES` scopes.

**1. Create the customer** (once), **then the invoice:**

```php
use Mulla\Params\InvoiceParams;

$customer = $mulla->customers->create([
    'full_name' => 'Jane Doe',
    'email'     => 'jane@example.com',
    'phone'     => '+250788000000',
]);

$invoice = $mulla->invoices->create(
    InvoiceParams::make()
        ->customer($customer->id)
        ->addItem('Website design', 150000)
        ->addItem('Hosting (months)', 10000, quantity: 12)
        ->dueDate('2026-12-31')
);
```

**2. Collect the payment.** This sends a payment prompt to the customer's phone:

```php
$mulla->invoices->pay($invoice->id, Provider::MTN_RW, '+250788000000');
```

**3. Track it.** You get an `invoice.paid` webhook once it's fully paid (`$invoice->isPaid()`). To see every payment attempt, call `$mulla->invoices->listPayments($invoice->id)`.

To cancel an invoice: `$mulla->invoices->updateStatus($invoice->id, InvoiceStatus::CANCELLED)`.

### List and filter records

Every `list()` method takes filters and returns one page. A `foreach` loop goes through **every page**, fetching more as it goes:

```php
use Mulla\Enums\ChargeStatus;

// Every successful charge since 1 November
foreach ($mulla->charges->list(['status' => ChargeStatus::SUCCESSFUL, 'start_date' => '2026-11-01']) as $charge) {
    echo $charge->reference, ' ', $charge->amount, PHP_EOL;
}

// One page of 20
$page = $mulla->charges->list(['limit' => 20, 'page' => 1]);
$page->data;       // the charges on this page
$page->total;      // how many match in total
$page->hasMore();  // true if there are more pages
$next = $page->nextPage(); // null on the last page
```

Common filters are `page`, `limit`, `status`, `keyword`, `start_date`, `end_date`, `sortField` and `sortDirection` (`'ASC'` or `'DESC'`). Each method's PHPDoc lists its exact filters.

### Handle errors

Every exception the SDK throws extends `Mulla\Exceptions\MullaException`, so you can catch them all at once or one type at a time:

```php
use Mulla\Exceptions\{InvalidRequestException, MullaException, PermissionException};

try {
    $mulla->payouts->initiate($params);
} catch (InvalidRequestException $e) {
    if ($e->getErrorCode() === 'INSUFFICIENT_BALANCE') {
        // Ask an admin to top up.
    }
} catch (PermissionException $e) {
    // The key is missing a scope, or this server's IP isn't allowed.
} catch (MullaException $e) {
    error_log($e->getMessage()); // includes the error code and, often, a hint on how to fix it
}
```

API exceptions have `getStatusCode()`, `getErrorCode()` and `getBody()` (the full error response). See [Exceptions](#exceptions) for the full list.

### Avoid duplicate payments

Networks fail. If a request to start a payment times out, you can't know whether Mulla received it. Sending it again might charge the customer twice.

The SDK protects you in two ways:

1. **It only retries when that's safe.** A request that starts a payment is retried only when Mulla clearly didn't process it. See [Retry rules](#retry-rules).
2. **Idempotency keys.** Every payment request carries a unique key. If you repeat a request with the same key, Mulla rejects the repeat instead of creating a second payment.

For the strongest protection, create the key once, save it with your order, and reuse it on every attempt:

```php
use Mulla\Exceptions\{ConnectionException, IdempotencyException};
use Mulla\Support\Uuid;

$order->mulla_key ??= Uuid::v4(); // save it with the order

try {
    $mulla->charges->initiate($params, ['idempotency_key' => $order->mulla_key]);
} catch (ConnectionException $e) {
    if ($e->requestMayHaveBeenSent()) {
        // Unknown outcome: wait for the webhook, or look the charge up, before asking again.
    }
} catch (IdempotencyException $e) {
    // A charge with this key already exists. Don't create another one.
}
```

### Test your integration

The SDK includes helpers in `Mulla\Testing` so your own tests never call Mulla.

**Fake API responses** with `FakeTransport`:

```php
use Mulla\MullaClient;
use Mulla\Testing\FakeTransport;

$fake = (new FakeTransport())->pushJson(['id' => 'chg_1', 'status' => 'PENDING', 'amount' => 5000]);
$mulla = new MullaClient(['api_key' => 'sk_test_abc_xyz', 'transport' => $fake]);

checkout($mulla); // your code

$fake->assertSent(fn ($request) => str_ends_with($request->url, '/charges/initiate'));
$fake->lastBody(); // the data your code sent
```

To test failures, queue `pushError(400, 'INSUFFICIENT_BALANCE')` or `pushConnectionError()` instead.

**Sign test webhooks** with `WebhookSigner`:

```php
use Mulla\Enums\WebhookEventType;
use Mulla\Testing\WebhookSigner;

['body' => $body, 'headers' => $headers] = WebhookSigner::event(
    WebhookEventType::CHARGE_SUCCESS,
    ['charge' => 'chg_1', 'reference' => 'CHG1', 'amount' => 5000, 'currency' => 'RWF', 'status' => 'SUCCESSFUL'],
    'my-test-secret', // the same secret your endpoint uses in tests
);
// Send $body with $headers to your webhook endpoint.
```

### Go live

Before you switch to real money, check that:

- [ ] your merchant account is approved in the Mulla dashboard;
- [ ] `MULLA_SECRET_KEY` is a live key (`sk_live_`) with only the scopes you need;
- [ ] you registered your webhook URL **with the live key** and saved the new live secret as `MULLA_WEBHOOK_SECRET`;
- [ ] your webhook URL uses HTTPS and returns `200` for `$mulla->webhookEndpoint->test()`;
- [ ] orders are marked paid only from a webhook or `retrieve()`, never from the return URL;
- [ ] your webhook endpoint skips events you've already handled;
- [ ] charges still `PENDING` after a while are treated as unpaid and checked with `retrieve()`, rather than waiting for a `charge.expired` webhook alone;
- [ ] if your key has an IP allowlist, your server's IP is on it.

## Reference

### Services and methods

| Service                   | Methods                                                                                                                         | Scope       |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | ----------- |
| `$mulla->charges`         | `initiate`, `createCheckout`, `retrieve`, `list`, `waitForFinalStatus`                                                          | `CHARGES`   |
| `$mulla->checkout`        | `retrieve`, `pay`, `eventsUrl` (public; uses the session token)                                                                 | —           |
| `$mulla->payouts`         | `initiate`, `retrieve`, `list`, `waitForFinalStatus`                                                                            | `PAYOUTS`   |
| `$mulla->recharges`       | `initiate`, `retrieve`, `list`, `waitForFinalStatus`                                                                            | `RECHARGES` |
| `$mulla->invoices`        | `create`, `retrieve`, `list`, `update`, `updateStatus`, `listPayments`, `retrievePayment`, `retrievePublic`, `pay`, `eventsUrl` | `INVOICES`  |
| `$mulla->customers`       | `create`, `retrieve`, `list`, `update`, `blacklist`, `whitelist`                                                                | `CUSTOMERS` |
| `$mulla->webhookEndpoint` | `create`, `retrieve`, `update`, `rotateSecret`, `test`                                                                          | —           |
| `$mulla->referenceData`   | `banks`, `bankBranches`, `countries`, `currencies`, `scopes`                                                                    | —           |

`waitForFinalStatus($id, $timeoutSeconds = 120, $intervalSeconds = 5)` checks every `$intervalSeconds` until the status is final or `$timeoutSeconds` has passed. Either way it returns the latest object, so check its status.

### Client options

Pass one array: `new MullaClient(['api_key' => $key, ...])`. If you only need the key, `new MullaClient('sk_test_...')` works too.

- Option names use snake_case. A name the SDK doesn't know throws an `InvalidArgumentException`; if you wrote `baseUrl`, the message suggests `base_url`.
- `null`, `false` and `''` mean "not set", so a missing `getenv()` value falls back to the default.
- Every option is checked when the client is created, so a mistake stops your app at startup instead of during a payment.

```php
$mulla = new MullaClient([
    'api_key' => getenv('MULLA_SECRET_KEY'),
    'environment' => Environment::PRODUCTION,
    'webhook_secret' => getenv('MULLA_WEBHOOK_SECRET'),
    'webhook_url' => 'https://shop.example/mulla/webhook',
    'timeout' => 10,
    'lang' => Language::EN,
    'app_info' => ['name' => 'my-shop', 'version' => '1.4.0'],
    'logger' => $psrLogger,
]);
```

| Option                 | Default              | Description                                                                                                         |
| ---------------------- | -------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `api_key`              | — (required)         | Your secret key, `sk_test_...` or `sk_live_...`.                                                                    |
| `environment`          | From the key         | `Environment::TEST` or `Environment::PRODUCTION`. A safety check: the client refuses a key from the other environment. |
| `base_url`             | From the key         | Server address. Must use HTTPS, except for local hosts such as `localhost`.                                         |
| `webhook_secret`       | —                    | Default secret for `$mulla->webhooks()`. Never logged or shown by `var_dump()`.                                     |
| `webhook_url`          | —                    | Default URL for `$mulla->webhookEndpoint->create()`. Must use HTTPS (plain http only for `localhost`).              |
| `timeout`              | `30`                 | Time limit for each request, in seconds.                                                                            |
| `connect_timeout`      | `10`                 | Time limit for opening the connection, in seconds.                                                                  |
| `max_retries`          | `2`                  | Extra tries for requests that failed for a temporary reason. `0` turns retries off.                                 |
| `lang`                 | API default          | Language of API messages: `Language::EN`, `FR`, `RW` or `SW` (or `'en'`, ...).                                      |
| `device_id`            | Made from the key    | A UUID v4, sent as the `x-device-id` header.                                                                        |
| `app_info`             | —                    | `['name' => ..., 'version' => ..., 'url' => ...]`, added to the User-Agent. Letters, numbers and `. _ ~ @ + -` only. |
| `logger`               | —                    | Any PSR-3 logger, or `fn (string $level, string $message, array $context)`. Keys are never logged; logger errors are ignored. |
| `middleware`           | `[]`                 | `fn (Request $request, callable $next): Response` functions or `MiddlewareInterface` objects, run on every request. |
| `transport`            | `CurlTransport`      | Replaces the part that sends HTTP requests, for example with Guzzle (see below) or `FakeTransport` in tests.         |
| `retry_strategy`       | Exponential backoff  | Rarely needed: your own retry rules. Can't be combined with `max_retries`.                                          |
| `environment_resolver` | Official servers     | Rarely needed: your own rules for choosing the server address.                                                      |
| `sleep`                | `usleep()`           | Rarely needed: how retries wait. Tests pass `fn () => null`.                                                        |

### Per-request options

Pass them as the last argument of any method, for example `$mulla->charges->initiate($params, ['lang' => 'rw'])`.

| Option            | Description                                                               |
| ----------------- | ------------------------------------------------------------------------- |
| `idempotency_key` | Your own UUID. See [Avoid duplicate payments](#avoid-duplicate-payments). |
| `timeout`         | Time limit for this request, in seconds.                                  |
| `lang`            | Language of this request's messages.                                      |
| `headers`         | Extra headers for this request.                                           |

### Environment variables

The SDK never reads environment variables or `.env` files, so a key can't be picked up by accident. Read them in your code and pass them in:

```php
$mulla = new MullaClient([
    'api_key' => getenv('MULLA_SECRET_KEY'),
    'base_url' => getenv('MULLA_BASE_URL'),
    'webhook_secret' => getenv('MULLA_WEBHOOK_SECRET'),
]);
```

### Statuses

A final status never changes again. Every status enum has `isFinal()`, for example `$charge->status?->isFinal()`.

| Object           | In progress                        | Final                                                     | Model helpers                 |
| ---------------- | ---------------------------------- | --------------------------------------------------------- | ----------------------------- |
| Charge, recharge | `PENDING`                          | `SUCCESSFUL`, `FAILED`, `EXPIRED`                         | `isSuccessful()`, `isFinal()` |
| Payout           | `PENDING`, `APPROVED`, `SCHEDULED` | `COMPLETED`, `FAILED`, `REJECTED`, `CANCELLED`, `EXPIRED` | `isSuccessful()`, `isFinal()` |
| Invoice          | `ISSUED`, `PENDING`, `OVERDUE`     | `PAID`, `CANCELLED`                                       | `isPaid()`                    |
| Invoice payment  | `PENDING`, `PROCESSING`            | `SUCCESSFUL`, `FAILED`, `CANCELLED`, `EXPIRED`            | `isSuccessful()`              |

Statuses are PHP enums (`Mulla\Enums\ChargeStatus` and so on). Where you pass a status in, a plain string works too.

### Webhook events

| Event            | Sent when                                  | `$event->objectId()` |
| ---------------- | ------------------------------------------ | -------------------- |
| `charge.success` | A charge succeeds                          | Charge ID            |
| `charge.failed`  | A charge fails                             | Charge ID            |
| `charge.expired` | A charge expires without an answer         | Charge ID            |
| `payout.success` | A payout completes                         | Payout ID            |
| `payout.failed`  | A payout fails                             | Payout ID            |
| `invoice.paid`   | An invoice is fully paid                   | Invoice ID           |
| `webhook.test`   | You call `$mulla->webhookEndpoint->test()` | —                    |

The `Event` object also has `id`, `type`, `data` (the payload) and the helpers `is()`, `isTest()`, `reference()`, `transactionId()`, `status()`, `amount()`, `currency()` and `metadata()`. `EventDispatcher` has `on()`, `onAny()` and `dispatch()`.

### Exceptions

All of them are in `Mulla\Exceptions` and extend `MullaException`. The ones with an HTTP status also extend `ApiException`.

| Exception                        | HTTP status | Common causes                                                           |
| -------------------------------- | ----------- | ----------------------------------------------------------------------- |
| `InvalidRequestException`        | 400         | Invalid or unknown parameters, amount below the minimum, low balance    |
| `AuthenticationException`        | 401         | The key is invalid, expired or revoked                                  |
| `PermissionException`            | 403         | Missing scope, IP not allowed, test/live mismatch, account not approved |
| `NotFoundException`              | 404         | No object with that ID                                                  |
| `ConflictException`              | 409         | A payment for this is already in progress                               |
| `IdempotencyException`           | 409         | The idempotency key was already used                                    |
| `RateLimitException`             | 429         | Too many requests. `getRetryAfter()` says how many seconds to wait      |
| `ApiServerException`             | 500–599     | A problem on Mulla's side                                               |
| `ConnectionException`            | —           | No response (network, DNS, timeout). Check `requestMayHaveBeenSent()`   |
| `SignatureVerificationException` | —           | A webhook signature is missing, wrong or too old                        |
| `InvalidArgumentException`       | —           | Wrong SDK usage (missing required field, empty ID…), before any request |

### Retry rules

The SDK retries a failed request up to `max_retries` times, waiting longer each time (from 0.5 s up to 8 s). It follows the server's `Retry-After` header when there is one.

| Request                                         | Retried after                                                                        |
| ----------------------------------------------- | ------------------------------------------------------------------------------------ |
| Reads (`retrieve`, `list`)                      | Network errors and statuses 408, 429, 500, 502, 503, 504                             |
| Writes (`initiate`, `create`, `pay`, `update`…) | Statuses 429 and 503 (rejected before processing), or a connection that never opened |

A write that times out or gets a 500 is **not** retried, because Mulla may already have sent the prompt or the money. Retries reuse the same idempotency key.

### Response objects

Methods return typed models (`Charge`, `Payout`, `Invoice`…) with camelCase properties, `DateTimeImmutable` dates, enum statuses and helpers such as `isSuccessful()`.

- `$model->toArray()` returns the untouched API data, and `json_encode($model)` does the same.
- `$model['some_field']` reads a field the SDK doesn't model yet.
- An unknown status or provider becomes `null` instead of failing, so new API values don't break your app.

### Using Guzzle or another PSR-18 client

```php
use Mulla\Http\Transport\Psr18Transport;

$mulla = new MullaClient([
    'api_key' => getenv('MULLA_SECRET_KEY'),
    'transport' => new Psr18Transport(new \GuzzleHttp\Client(['timeout' => 30]), new \GuzzleHttp\Psr7\HttpFactory()),
]);
```

### Security

- The base URL must use `https://`. Plain `http://` works only for local hosts (`localhost`, `127.0.0.1`, `*.test`, `host.docker.internal`).
- The secret key never appears in logs, exception messages or `var_dump`/`print_r` output. Checkout tokens are masked (`token=***`).
- TLS certificates are always verified. Extra cURL options (proxy, CA bundle) can't turn verification off.
- Webhook signatures are compared in constant time.

## Troubleshooting

| Problem                                                        | Likely cause and fix                                                                                                      |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `SignatureVerificationException` for every webhook             | The body was changed before verifying. Use the raw body (`file_get_contents('php://input')` or `$request->getContent()`). |
| `SignatureVerificationException` after it used to work         | Wrong secret. Test and live endpoints have different secrets, and `rotateSecret()` replaces the old one.                  |
| Webhook gets `419` or `403` from your framework                | CSRF protection is blocking it. Exclude the webhook route.                                                                |
| `PermissionException` with `API_KEY_SCOPE_DENIED`              | The key lacks the scope for that service. Add it in the dashboard.                                                        |
| `PermissionException` with `API_KEY_ENVIRONMENT_MISMATCH`      | A test key is calling production, or the other way round. Remove `base_url` and let the key decide.                        |
| `PermissionException` with `API_KEY_IP_DENIED`                 | Your server's IP isn't on the key's allowlist.                                                                            |
| `VALIDATION_FAILED` with "Invalid Phone Number"                | The number has no leading `+`. Send `+250788000000`, not `250788000000` or `0788000000`.                                  |
| `InvalidRequestException` with `VALIDATION_FAILED`             | A field is invalid, misspelled or unknown. Array keys must be snake_case, such as `phone_number`.                         |
| `TypeError` or `InvalidArgumentException` about the API key    | `getenv('MULLA_SECRET_KEY')` returned `false` because the variable isn't set for this PHP process.                        |
| `InvalidArgumentException` about the base URL                  | `base_url` must use HTTPS, except for local hosts.                                                                         |
| `waitForFinalStatus()` returns an object that is still pending | It timed out (after 120 seconds by default) and returned the latest state. Rely on webhooks, or raise the timeout.        |
| Charges end up `EXPIRED`                                       | The customer didn't approve the prompt in time. Ask them to check their phone, then start a new charge.                   |

## Versioning and compatibility

<!-- x-release-please-start-version -->

| Package version | Mulla API | Tested in CI on             |
| --------------- | --------- | --------------------------- |
| 0.1.0           | v1        | PHP 8.1, 8.2, 8.3, 8.4, 8.5 |

<!-- x-release-please-end -->

- The package follows [semantic versioning](https://semver.org). Until 1.0.0, a minor release (0.**x**.0) may contain breaking changes. Patch releases never do.
- Every change is listed in [CHANGELOG.md](CHANGELOG.md).

## Support

- **Bugs and feature requests:** [open an issue](https://github.com/kaykorw/mulla-php-sdk/issues). Include the SDK version, your PHP version (`php -v`), and the error's code and message. Never paste your API key or webhook secret.
- **Your account, payments or API keys:** contact Mulla support through your Mulla dashboard.
- **Security problems:** don't open a public issue. Report them privately through the repository's [Security tab](https://github.com/kaykorw/mulla-php-sdk/security).

## Contributing

Contributions are welcome. For anything bigger than a small fix, open an issue first so we can agree on the approach.

**1. Set up** (PHP 8.1 or newer and Composer):

```bash
git clone https://github.com/kaykorw/mulla-php-sdk.git
cd mulla-php-sdk
composer install
```

**2. Make your change**, with tests in `tests/Unit`. Update this README if the public API changes.

**3. Check everything** before you open a pull request:

```bash
composer check   # code style, static analysis and unit tests
```

Other commands:

| Command                     | What it does                                                   |
| --------------------------- | -------------------------------------------------------------- |
| `composer test`             | Run the unit tests                                             |
| `composer analyse`          | Static analysis (PHPStan level 8)                              |
| `composer cs`               | Check code style                                               |
| `composer cs:fix`           | Fix code style                                                 |
| `composer test:integration` | Test against the sandbox. Needs `MULLA_SECRET_KEY=sk_test_...` |

**4. Open a pull request** against `main`. CI must pass.

**Rules:**

- **No new required dependencies.**
- **Same behaviour as the other Mulla SDKs:** same services, method names, errors, retry rules and webhook checks.
- **Keep PHP 8.1 support.**
- **[Conventional Commits](https://www.conventionalcommits.org)** for commit messages (`feat:`, `fix:`, `docs:`, `chore:`). Releases and the changelog are generated from them, so `feat:` and `fix:` commits appear in the next release.

## License

MIT. See [LICENSE](LICENSE).
