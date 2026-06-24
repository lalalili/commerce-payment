# lalalili/commerce-payment

![CI](https://github.com/lalalili/commerce-payment/actions/workflows/ci.yml/badge.svg)

Unified, configurable Taiwan payment package for Laravel commerce applications — ECPay
(credit + UnionPay) and E.SUN Bank — with **pluggable reconciliation**.

This package consolidates the earlier `lalalili/payment` and `lalalili/payment-ecpay` into a
single, configurable payment library. Gateways are **pure communication** (call the bank API,
verify signatures, return a normalized `PaymentResult`); order reconciliation is decoupled
behind a host-pluggable `PaymentReconciler`.

## Architecture

```
config('commerce-payment.methods.*')  →  PaymentManager::gateway('ecpay_credit' | 'ecpay_unionpay' | 'esun')
gateway.checkout()                    →  PaymentStartResult  (form/redirect, no order writes)
gateway.handleNotify/Return/query()   →  PaymentResult       (normalized, no order writes)
PaymentManager::reconcile(result)     →  bound PaymentReconciler  →  your order layer
```

- **Configurable methods**: each `commerce-payment.methods.<key>` entry is `driver` (`ecpay`/`esun`)
  plus that method's settings (UnionPay, TradeDesc, ItemName limit, credentials, …). The same
  `EcpayGateway` serves credit (`union_pay => 2`) and UnionPay (`union_pay => 1`).
- **Pluggable reconciliation**: bind `Lalalili\CommercePayment\Contracts\PaymentReconciler`.
  - commerce-core hosts: bind `Reconcilers\CommerceCoreReconciler` (needs `lalalili/commerce-core`).
  - other hosts: bind your own adapter wrapping your order service.

`PaymentOutcome`: `Paid` / `Declined` / `Refunded` / `UserCancelled` / `Pending` / `QueryFailed`.

## Status

**Stable (v1.x).** Production-validated by both `cptw` and `aitehub` across payment, invoice,
and reconciliation. The predecessors `lalalili/payment` and `lalalili/payment-ecpay` are
deprecated, abandoned, and archived in favour of this package.

## Public API & Semantic Versioning

From v1.0.0 the following surface is covered by [SemVer](https://semver.org/) — breaking changes
to it require a new major version:

- **Contracts**: `Contracts\PaymentGateway`, `Contracts\PaymentReconciler`, `Contracts\InvoiceGateway`.
- **Data DTOs** (constructors, named factories, public readonly properties): `Data\PaymentResult`,
  `Data\RefundResult`, `Data\PaymentStartResult`, `Data\InvoiceResult`.
- **Enum**: `Enums\PaymentOutcome` cases.
- **Event**: `Events\PaymentResultReceived`.
- **Entry points**: `PaymentManager` (`gateway()`, `reconcile()`) and the shipped
  `Reconcilers\*` adapters' public methods.
- **Config schema** (`config/commerce-payment.php`): the `methods.<key>.{driver,…}` registry
  shape, `reconcile`, `refund`, and `invoice` keys.

Not covered (may change in a minor): `Support\*` internals, gateway-private helpers, and the
concrete SDK request payloads. New payment methods, channels, or additive contracts (e.g. a
future subscription module or `InvoiceReconciler`) ship as minor releases.

## License

MIT.
