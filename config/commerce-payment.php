<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 支付方式（method registry）
    |--------------------------------------------------------------------------
    | 每個 key 是一個支付方式：driver（ecpay/esun）+ 該方式的設定。
    | PaymentManager::gateway('<key>') 依此解析對應 gateway。
    */
    'methods' => [
        'ecpay_credit' => [
            'driver'             => 'ecpay',
            'merchant_id'        => env('ECPAY_PAYMENT_MERCHANT_ID', ''),
            'hash_key'           => env('ECPAY_PAYMENT_KEY', ''),
            'hash_iv'            => env('ECPAY_PAYMENT_IV', ''),
            'union_pay'          => 2,
            'choose_payment'     => 'Credit',
            'trade_desc'         => '信用卡付款',
            'item_name_limit'    => 300,
            'gateway_label'      => '信用卡',
            'stage_merchant_ids' => ['3002607', '3002599'],
        ],

        'ecpay_unionpay' => [
            'driver'             => 'ecpay',
            'merchant_id'        => env('ECPAY_PAYMENT_MERCHANT_ID', ''),
            'hash_key'           => env('ECPAY_PAYMENT_KEY', ''),
            'hash_iv'            => env('ECPAY_PAYMENT_IV', ''),
            'union_pay'          => 1,
            'choose_payment'     => 'Credit',
            'trade_desc'         => '銀聯卡支付',
            'item_name_limit'    => 200,
            'gateway_label'      => '銀聯卡',
            'stage_merchant_ids' => ['3002607', '3002599'],
        ],

        'esun' => [
            'driver'          => 'esun',
            'mid'             => env('ESUN_MID', ''),
            'mac_key'         => env('ESUN_MACKEY', ''),
            'tid'             => env('ESUN_TID', 'EC000001'),
            'order_url'       => env('ESUN_ORDER_URL', ''),
            'query_url'       => env('ESUN_QUERY_URL', ''),
            'cancel_url'      => env('ESUN_CANCEL_URL', ''),
            'status_messages' => [],
            'gateway_label'   => '信用卡',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 對帳（reconciliation）
    |--------------------------------------------------------------------------
    | host 須 bind Lalalili\CommercePayment\Contracts\PaymentReconciler 實作
    | （commerce-core host 可綁 CommerceCoreReconciler）。
    */
    'reconcile' => [
        'strict_amount_check' => env('COMMERCE_PAYMENT_STRICT_AMOUNT_CHECK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 退款（refund）
    |--------------------------------------------------------------------------
    | auto_on_cancel：各專案自行決定「取消訂單時是否自動呼叫渠道退款 API」。
    | 套件不主動退款；host 在取消流程讀此旗標決定是否呼叫 CommerceCoreRefundSyncService。
    | 預設 false（如 cptw 退款走人工會計）。
    */
    'refund' => [
        'auto_on_cancel' => env('COMMERCE_PAYMENT_REFUND_AUTO_ON_CANCEL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | 電子發票（ECPay B2C invoice，獨立 merchant + 金鑰）
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'merchant_id'        => env('ECPAY_INVOICE_MERCHANT_ID', ''),
        'hash_key'           => env('ECPAY_INVOICE_KEY', ''),
        'hash_iv'            => env('ECPAY_INVOICE_IV', ''),
        'stage_merchant_ids' => ['2000132', '3085340'],
    ],
];
