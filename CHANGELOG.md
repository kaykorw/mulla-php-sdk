# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [0.0.1] - Unreleased

### Added

- `MullaClient` with services: charges, checkout, payouts, recharges, invoices, customers, webhookEndpoint, referenceData.
- Automatic idempotency keys, retries with exponential backoff, required client-context headers.
- Typed models, enums and an auto-paging `Collection`.
- Exception hierarchy mapped from HTTP status and Mulla `errorCode`.
- Webhook verification (v1 HMAC-SHA512, timestamped v2 ready) and `EventDispatcher`.
- Testing helpers: `FakeTransport`, `WebhookSigner`.
- `invoices->eventsUrl()` for the public invoice payment-status stream.
- `ConnectionException::requestMayHaveBeenSent()`.

### Security

- Writes are only retried when Mulla cannot have processed them (429, 503, connection never made),
  preventing duplicate charges and payouts after timeouts or 5xx responses.
- The base URL must use HTTPS (plain HTTP only for local hosts); credentials, query strings and fragments are rejected.
  cURL is restricted to HTTP(S) and redirects are not followed.
- Header values with line breaks are rejected; idempotency keys must be UUIDs.
- The API key is masked in `var_dump`/`print_r` output of the client, config, middleware and requests.
- Checkout tokens are redacted from logs and connection error messages.
- Custom cURL options can no longer override TLS verification, URL, method, headers or body.
- Ids `.` and `..` are rejected; invalid UTF-8 parameters raise `InvalidArgumentException`.
- `curl_close()` removed (deprecated in PHP 8.5); CI now tests PHP 8.5.
