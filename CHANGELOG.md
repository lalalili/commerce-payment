# Changelog

All notable changes to `lalalili/commerce-payment` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2026-06-27

### Added

- **ECPay 信用卡定期定額（Credit Period / recurring）** — 新增 `RecurringPaymentGateway` 選用 contract
  （與一般 `PaymentGateway` 分離，不影響不支援此功能的 `EsunGateway`），由 `EcpayGateway` 實作：
  - `startRecurring()` — 在 AIO 之上帶 `PeriodAmount(=TotalAmount)/PeriodType/Frequency/ExecTimes/PeriodReturnURL`。
  - `verifyRecurringNotify()` / `handleRecurringNotify()` — 解析第 2 期起 `PeriodReturnURL` 單期通知
    （`TotalSuccessTimes`、`gwsr`）。
  - `cancelRecurring()` — 呼叫 `CreditCardPeriodAction (Action=Cancel)` 停止後續扣款。
  - `queryRecurring()` — 查詢定期定額狀態（`QueryCreditCardPeriodInfo`）。
- 新增 `Data\RecurringPaymentResult`、`Data\RecurringActionResult` DTO。
- `EcpayCheckoutPayloadFactory::makeRecurring()` 與 config `recurring.cycles` 預設（月 999 / 年 99 期）。

## [1.3.2] - 2026-06-25

### Fixed

- **E.SUN refund detection** — `EsunGateway` classified refunds by `RC === '69'`, but `69` is a
  `SETTLESTATUS` (訂單狀態：退貨成功), not a response code (`RC` has no `69` — see EsunACQ error-code
  spec). The branch was dead, so externally-processed refunds surfaced as `Pending` instead of
  `Refunded`. Now keyed on `SETTLESTATUS === '69'`. Verified against the E.SUN ACQ docs
  (單筆查詢 附件一 訂單狀態 / 交易錯誤定義碼).

## [1.3.1] - 2026-06-25

### Changed

- Relaxed the `lalalili/commerce-core` compatibility constraint.

## [1.3.0] - 2026-06-25

### Added

- Delegated payment application to the commerce-core service.

## [1.2.0] - 2026-06-24

### Added

- `PaymentGateway::verifyReturn(Request): bool` — verifies a browser redirect (OrderResultURL)
  signature without querying or reconciling, symmetric with `verifyNotify`. Lets a host redirect
  to an error page (and skip reconciliation) when a return is unverified. ECPay checks the
  CheckMacValue yields a `MerchantTradeNo`; E.SUN (no return signature) confirms the `DATA`
  parses to an order number, deferring trust to the server-to-server query MAC. Surfaced by the
  aitehub subscription migration.

## [1.1.0] - 2026-06-24

### Added

- `InvoiceGateway::getIssue(string $relateNumber): array` — ECPay B2C **GetIssue** detail query
  (sends `RqHeader.Revision = '3.0.0'`), returning the raw decrypted response so the host can map
  the richer `Data.IIS_*` detail fields (issue/invalid/upload status, carrier, love code, …).
  Distinct from `query()`, which returns an Issue-shaped `InvoiceResult` summary.
- `EcpayInvoiceGateway::post()` now accepts an optional per-call `Revision`, enabling the 3.0.0
  endpoints (additive; existing calls are unchanged).

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
