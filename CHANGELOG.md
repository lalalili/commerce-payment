# Changelog

All notable changes to `lalalili/commerce-payment` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-06-24

### Added

- `InvoiceGateway::void()` now merges an optional `invoice_fields` array into the request `Data`
  (e.g. some merchants must send `InvoiceDate` when voiding). Surfaced by the cptw adoption.

## [0.2.0] - 2026-06-24

### Added

- **Invoice module (ECPay B2C e-invoice)** — the shared core between cptw and aitehub:
  - `Contracts\InvoiceGateway` (`issue`/`void`/`query` + `checkLoveCode`/`checkBarcode`/`companyName`)
    and `Data\InvoiceResult`. Host-agnostic: the carrier/donation mapping is passed in as
    pre-resolved `invoice_fields`.
  - `Gateways\EcpayInvoiceGateway` — pure AES-JSON communication, config-injected.
  - `Reconcilers\CommerceCoreInvoiceSyncService` — issues per tax group and persists to
    commerce-core `OrderInvoice` (requires `lalalili/commerce-core`, suggested).
  - `EcpayEndpointResolver::invoiceBaseUrl()`; `config('commerce-payment.invoice.*')`.

Allowance / print / notify (cptw-only) are intentionally out of scope for now.

## [0.1.1] - 2026-06-24

### Added

- `PaymentGateway::verifyNotify(Request): bool` — verify a background notification's signature
  without querying, for hosts that ack fast and reconcile asynchronously (avoids ReturnURL
  timeouts). ECPay verifies the CheckMacValue; E.SUN returns `false` (no background notify).

## [0.1.0] - 2026-06-24

### Added

- Unified, configurable Taiwan payment package consolidating `lalalili/payment` and
  `lalalili/payment-ecpay`.
- `PaymentManager` with a config-driven method registry (`commerce-payment.methods.*`):
  one `EcpayGateway` serves both credit and UnionPay via method options; plus `EsunGateway`.
- Pure-communication `PaymentGateway` contract (`checkout`/`handleNotify`/`handleReturn`/`query`/`refund`)
  returning a normalized `PaymentResult` / `RefundResult`; gateways never touch orders.
- Pluggable `PaymentReconciler` contract — the seam that decouples reconciliation from the
  order layer. Ships `CommerceCoreReconciler` (requires `lalalili/commerce-core`, suggested);
  non-commerce-core hosts bind their own.
- `PaymentResultReceived` event as an alternative reconciliation seam.
- `PaymentOutcome` enum, `PaymentStartResult`, configurable `EcpayCheckoutPayloadFactory`,
  `EcpayEndpointResolver`, HTTP/auto-submit-form support traits.
