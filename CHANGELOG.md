# Changelog

All notable changes to `lalalili/commerce-payment` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-06-24

### Changed

- **Stable release.** No code changes from `0.2.3`; this release promotes the package to a
  SemVer-stable `1.x` line after both `cptw` and `aitehub` migrated onto it in production
  (payment, invoice, and reconciliation). The deprecated predecessors `lalalili/payment` and
  `lalalili/payment-ecpay` are abandoned and archived.
- Documented the SemVer-covered public API surface in the README (contracts, data DTOs,
  `PaymentOutcome`, `PaymentResultReceived`, `PaymentManager`, shipped reconcilers, and the
  `config/commerce-payment.php` schema). Additive work (subscription module, `InvoiceReconciler`,
  new channels) will ship as minor `1.x` releases.

## [0.2.3] - 2026-06-24

### Changed

- `CommerceCoreRefundSyncService` now **only refunds** (calls the channel refund API and records a
  `refund:*` log); it no longer cancels the order. Cancelling an order is the host's action; whether
  a refund follows is each project's decision via `config('commerce-payment.refund.auto_on_cancel')`
  (default `false`). The host reads this flag in its cancel flow to decide whether to call this service.

### Added

- `config('commerce-payment.refund.*')` with `auto_on_cancel`.

## [0.2.2] - 2026-06-24

### Added

- `Reconcilers\CommerceCoreRefundSyncService` — refunds a commerce-core order via the gateway's
  pure `refund()` (resolving `trade_no` from commerce-core `PaymentLog`), records a `refund:*`
  payment log, and cancels the order on success. Requires `lalalili/commerce-core` (suggested).

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
