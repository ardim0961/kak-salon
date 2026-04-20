<?php
// File: config/midtrans_config.php

// Midtrans Configuration
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-XXXXXXXXXXXXXXXXXXXX');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-XXXXXXXXXXXXXXXXXXXX');
define('MIDTRANS_MERCHANT_ID', 'GXXXXXXXX');
define('MIDTRANS_IS_PRODUCTION', false); // Set to true for production

// Midtrans URLs
define('MIDTRANS_SANDBOX_BASE_URL', 'https://api.sandbox.midtrans.com/v2/charge');
define('MIDTRANS_PRODUCTION_BASE_URL', 'https://api.midtrans.com/v2/charge');

// Helper function to get Midtrans config
function getMidtransConfig() {
    return [
        'server_key' => MIDTRANS_SERVER_KEY,
        'client_key' => MIDTRANS_CLIENT_KEY,
        'is_production' => MIDTRANS_IS_PRODUCTION,
        'merchant_id' => MIDTRANS_MERCHANT_ID
    ];
}

// Function to create Midtrans payment (simplified for testing)
function createMidtransPayment($order_id, $amount, $customer_details, $item_details = []) {
    // For testing purposes, we'll simulate a successful payment
    // In production, you would use actual Midtrans API

    $payment_data = [
        'transaction_details' => [
            'order_id' => $order_id,
            'gross_amount' => $amount
        ],
        'customer_details' => $customer_details,
        'item_details' => $item_details,
        'payment_type' => 'qris',
        'qris' => [
            'acquirer' => 'gopay'
        ]
    ];

    // Simulate API response
    $response = [
        'status_code' => '201',
        'status_message' => 'Success, QRIS transaction is created',
        'transaction_id' => 'fake-transaction-' . time(),
        'order_id' => $order_id,
        'gross_amount' => $amount,
        'payment_type' => 'qris',
        'transaction_time' => date('Y-m-d H:i:s'),
        'transaction_status' => 'pending',
        'qr_string' => '00020101021126690013ID.CO.BCA.WWW011893600014000052603030141540620050055020057036005802ID5914SK HAIR SALON6015Bandung, Jawa 60256010493600014000052605406' . str_pad($amount, 10, '0', STR_PAD_LEFT) . '55020153033605802ID5900SK HAIR SALON6007Bandung6106401126304ABCD',
        'actions' => [
            [
                'name' => 'generate-qr-code',
                'method' => 'GET',
                'url' => 'https://api.sandbox.midtrans.com/v2/qris/' . $order_id . '/qr-code'
            ]
        ]
    ];

    return $response;
}

// Function to check payment status (simplified for testing)
function checkMidtransPaymentStatus($order_id) {
    // For testing, simulate different statuses
    $statuses = ['pending', 'settlement', 'expire', 'cancel'];

    // Simulate status based on time (for demo purposes)
    $time_diff = time() - strtotime('today');
    $status_index = ($time_diff / 300) % count($statuses); // Change every 5 minutes

    $status = $statuses[floor($status_index)];

    $response = [
        'status_code' => '200',
        'status_message' => 'Success, transaction found',
        'transaction_id' => 'fake-transaction-' . time(),
        'order_id' => $order_id,
        'payment_type' => 'qris',
        'transaction_time' => date('Y-m-d H:i:s'),
        'transaction_status' => $status,
        'gross_amount' => '50000.00'
    ];

    return $response;
}
?>