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
