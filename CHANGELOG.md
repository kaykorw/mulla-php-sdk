# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [0.1.0](https://github.com/kaykorw/mulla-php-sdk/compare/v0.0.2...v0.1.0) (2026-11-27)


### Features

* Refactor MullaClient to accept options as a single array and improve error handling ([00748be](https://github.com/kaykorw/mulla-php-sdk/commit/00748be649257d724cdbee938c2b3c38e5749796))


### Miscellaneous Chores

* MullaClient and enhance error handling and validation ([e7e5ac7](https://github.com/kaykorw/mulla-php-sdk/commit/e7e5ac7a73f6c7db929f2685f93d93c72640ef85))

## [0.0.2](https://github.com/kaykorw/mulla-php-sdk/compare/v0.0.1...v0.0.2) (2026-11-19)


### Miscellaneous Chores

* Update release-please configuration to include README.md as an extra file ([579d996](https://github.com/kaykorw/mulla-php-sdk/commit/579d9961ddda641830d381787c9f88d59b5421a6))

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
