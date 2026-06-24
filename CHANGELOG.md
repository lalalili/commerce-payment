# Changelog

All notable changes to `lalalili/commerce-payment` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
