<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\Fixtures;

/**
 * Mock API Response Fixtures for Testing
 */
class ApiResponses
{
    /**
     * Successful payment creation response
     */
    public static function successfulPaymentCreation(string $transactionId = 'TXN_TEST_12345'): array
    {
        return [
            'success' => true,
            'result' => [
                'transactionId' => $transactionId,
                'gatewayId' => 'GW_' . substr(md5($transactionId), 0, 8),
                'status' => 'PENDING',
                'amount' => 50.00,
                'currency' => 'EUR',
                'merchantReference' => 'ORDER_' . time(),
                'createdAt' => date('Y-m-d\TH:i:s\Z')
            ],
            'processId' => 'PROC_' . uniqid(),
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Successful capture response
     */
    public static function successfulCapture(string $transactionId = 'TXN_TEST_12345'): array
    {
        return [
            'success' => true,
            'result' => [
                'transactionId' => $transactionId,
                'gatewayId' => 'GW_' . substr(md5($transactionId), 0, 8),
                'status' => 'PROCESSED',
                'amount' => 150.00,
                'currency' => 'EUR',
                'authCode' => 'AUTH' . rand(100000, 999999),
                'capturedAt' => date('Y-m-d\TH:i:s\Z')
            ],
            'processId' => 'PROC_' . uniqid(),
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Successful refund response
     */
    public static function successfulRefund(string $transactionId = 'TXN_TEST_12345'): array
    {
        return [
            'success' => true,
            'result' => [
                'transactionId' => $transactionId,
                'gatewayId' => 'GW_' . substr(md5($transactionId), 0, 8),
                'status' => 'REFUNDED',
                'amount' => 25.00,
                'currency' => 'EUR',
                'refundedAt' => date('Y-m-d\TH:i:s\Z')
            ],
            'processId' => 'PROC_' . uniqid(),
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Get transaction details response
     */
    public static function transactionDetails(
        string $transactionId = 'TXN_TEST_12345',
        string $status = 'PROCESSED'
    ): array {
        return [
            'success' => true,
            'result' => [
                'transactionId' => $transactionId,
                'gatewayId' => 'GW_' . substr(md5($transactionId), 0, 8),
                'status' => $status,
                'transactionType' => 'SALE',
                'amount' => 50.00,
                'currency' => 'EUR',
                'customerEmail' => 'test@example.com',
                'customerFirstName' => 'John',
                'customerLastName' => 'Doe',
                'cardNumber' => '4111****1111',
                'cardScheme' => 'VISA',
                'authCode' => 'AUTH' . rand(100000, 999999),
                'bankResponse' => 'APPROVED',
                'threeDSecure' => [
                    'authenticated' => true,
                    'eci' => '05',
                    'cavv' => base64_encode('test_cavv')
                ],
                'createdAt' => date('Y-m-d\TH:i:s\Z', strtotime('-1 hour')),
                'processedAt' => date('Y-m-d\TH:i:s\Z')
            ],
            'processId' => 'PROC_' . uniqid(),
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Transaction list response
     */
    public static function transactionList(): array
    {
        return [
            'success' => true,
            'result' => [
                'transactions' => [
                    [
                        'transactionId' => 'TXN_TEST_001',
                        'status' => 'PROCESSED',
                        'amount' => 25.00,
                        'currency' => 'EUR',
                        'createdAt' => date('Y-m-d\TH:i:s\Z', strtotime('-2 hours'))
                    ],
                    [
                        'transactionId' => 'TXN_TEST_002',
                        'status' => 'PROCESSED',
                        'amount' => 50.00,
                        'currency' => 'EUR',
                        'createdAt' => date('Y-m-d\TH:i:s\Z', strtotime('-1 hour'))
                    ],
                    [
                        'transactionId' => 'TXN_TEST_003',
                        'status' => 'DECLINED',
                        'amount' => 100.00,
                        'currency' => 'EUR',
                        'createdAt' => date('Y-m-d\TH:i:s\Z', strtotime('-30 minutes'))
                    ]
                ],
                'totalCount' => 3,
                'pageSize' => 50,
                'pageNumber' => 1
            ],
            'processId' => 'PROC_' . uniqid(),
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Authentication error response (401)
     */
    public static function authenticationError(): array
    {
        return [
            'success' => false,
            'message' => 'Authentication failed. Invalid API key.',
            'errorCode' => 'AUTH_FAILED',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Validation error response (422)
     */
    public static function validationError(): array
    {
        return [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => [
                'Amount' => 'The Amount field is required.',
                'UniqueReference' => 'The UniqueReference field is required.',
                'CustomerEmail' => 'The CustomerEmail field must be a valid email address.'
            ],
            'errorCode' => 'VALIDATION_ERROR',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Server error response (500)
     */
    public static function serverError(): array
    {
        return [
            'success' => false,
            'message' => 'Internal server error. Please try again later.',
            'errorCode' => 'SERVER_ERROR',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Transaction not found error (404)
     */
    public static function transactionNotFound(): array
    {
        return [
            'success' => false,
            'message' => 'Transaction not found',
            'errorCode' => 'NOT_FOUND',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];
    }

    /**
     * Webhook payload - successful payment
     */
    public static function webhookProcessedPayment(string $orderRef = 'ORDER_12345'): array
    {
        return [
            'transactionId' => 'TXN_WEBHOOK_' . uniqid(),
            'gatewayId' => 'GW_' . substr(md5($orderRef), 0, 8),
            'status' => 'PROCESSED',
            'transactionType' => 'SALE',
            'amount' => 50.00,
            'currency' => 'EUR',
            'authCode' => 'AUTH' . rand(100000, 999999),
            'cardNumber' => '4111****1111',
            'cardScheme' => 'VISA',
            'cardCountry' => 'MT',
            'bankResponse' => 'APPROVED',
            'uniqueReference' => $orderRef,
            'customerEmail' => 'customer@example.com',
            'customerFirstName' => 'John',
            'customerLastName' => 'Doe',
            'threeDSecure' => [
                'authenticated' => true,
                'eci' => '05',
                'cavv' => base64_encode('test_cavv'),
                'xid' => base64_encode('test_xid')
            ],
            'UDF1' => 'Order ID: 123',
            'UDF2' => 'Customer ID: 456',
            'processedAt' => date('Y-m-d\TH:i:s\Z'),
            'merchantId' => 'MERCHANT_TEST_001'
        ];
    }

    /**
     * Webhook payload - declined payment
     */
    public static function webhookDeclinedPayment(string $orderRef = 'ORDER_12345'): array
    {
        return [
            'transactionId' => 'TXN_DECLINED_' . uniqid(),
            'gatewayId' => 'GW_' . substr(md5($orderRef), 0, 8),
            'status' => 'DECLINED',
            'transactionType' => 'SALE',
            'amount' => 50.00,
            'currency' => 'EUR',
            'cardNumber' => '4111****1111',
            'cardScheme' => 'VISA',
            'bankResponse' => 'INSUFFICIENT_FUNDS',
            'declineReason' => 'Insufficient funds',
            'uniqueReference' => $orderRef,
            'customerEmail' => 'customer@example.com',
            'processedAt' => date('Y-m-d\TH:i:s\Z'),
            'merchantId' => 'MERCHANT_TEST_001'
        ];
    }

    /**
     * Webhook payload - authorized payment (pre-auth)
     */
    public static function webhookAuthorizedPayment(string $orderRef = 'BOOKING_12345'): array
    {
        return [
            'transactionId' => 'TXN_AUTH_' . uniqid(),
            'gatewayId' => 'GW_' . substr(md5($orderRef), 0, 8),
            'status' => 'AUTHORIZED',
            'transactionType' => 'AUTH',
            'amount' => 150.00,
            'currency' => 'EUR',
            'authCode' => 'AUTH' . rand(100000, 999999),
            'cardNumber' => '5555****4444',
            'cardScheme' => 'MASTERCARD',
            'bankResponse' => 'APPROVED',
            'uniqueReference' => $orderRef,
            'customerEmail' => 'guest@hotel.com',
            'threeDSecure' => [
                'authenticated' => true,
                'eci' => '02'
            ],
            'processedAt' => date('Y-m-d\TH:i:s\Z'),
            'merchantId' => 'MERCHANT_TEST_001'
        ];
    }
}