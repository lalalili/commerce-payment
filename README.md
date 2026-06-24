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

P0 (core): ECPay credit/UnionPay + E.SUN gateways, method registry, `PaymentReconciler` +
`CommerceCoreReconciler`. Invoice and refund-reconciler modules, plus the optional callback
controller/routes, land in follow-up releases. cptw and aitehub migrate onto this package in
later phases; `lalalili/payment` and `lalalili/payment-ecpay` will then be deprecated.

## License

MIT.
