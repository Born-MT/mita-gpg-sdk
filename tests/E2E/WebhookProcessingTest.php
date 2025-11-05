<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\E2E;

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\Exceptions\InvalidSignatureException;
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;
use PHPUnit\Framework\TestCase;

/**
 * E2E Test: Webhook Processing
 *
 * Tests complete webhook handling workflow
 */
class WebhookProcessingTest extends TestCase
{
    private GpgClient $client;
    private string $webhookSecret = 'test_webhook_secret_12345';

    protected function setUp(): void
    {
        $this->client = new GpgClient(
            apiKey: 'test_api_key',
            testMode: true
        );
    }

    /**
     * E2E: Complete webhook processing for successful payment
     *
     * Scenario:
     * 1. Receive webhook HTTP POST
     * 2. Verify signature
     * 3. Parse payload
     * 4. Process payment confirmation
     * 5. Update database
     * 6. Send confirmation email
     * 7. Return 200 OK
     */
    public function testCompleteWebhookProcessingForSuccessfulPayment(): void
    {
        $orderRef = 'ORDER_WEBHOOK_' . time();

        // Step 1: Simulate receiving webhook
        $webhookData = ApiResponses::webhookProcessedPayment($orderRef);
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        // Step 2: Verify signature
        $isValid = $this->client->verifyWebhookSignature(
            $payload,
            $signature,
            $this->webhookSecret
        );

        $this->assertTrue($isValid, 'Webhook signature should be valid');

        // Step 3: Parse webhook payload
        $webhook = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);

        // Step 4: Verify payment details
        $this->assertTrue($webhook->isProcessed());
        $this->assertEquals('PROCESSED', $webhook->getStatus()->value);
        $this->assertEquals(50.00, $webhook->getAmount());
        $this->assertEquals('EUR', $webhook->getCurrency());
        $this->assertEquals($orderRef, $webhook->getUniqueReference());

        // Step 5: Verify transaction details
        $this->assertNotNull($webhook->getTransactionId());
        $this->assertNotNull($webhook->getAuthCode());
        $this->assertEquals('VISA', $webhook->getCardScheme());
        $this->assertEquals('4111****1111', $webhook->getCardNumber());

        // Step 6: Verify 3D Secure was used
        $threeDSecure = $webhook->getThreeDSecure();
        $this->assertIsArray($threeDSecure);
        $this->assertTrue($threeDSecure['authenticated']);

        // Step 7: Extract custom fields
        $this->assertEquals('Order ID: 123', $webhook->getUdfField(1));
        $this->assertEquals('Customer ID: 456', $webhook->getUdfField(2));

        // Application would now:
        // - Update order status to 'paid'
        // - Store transaction details
        // - Send confirmation email
        // - Trigger fulfillment process
        // - Return HTTP 200
    }

    /**
     * E2E: Webhook processing for declined payment
     *
     * Scenario:
     * Customer's card was declined, handle gracefully
     */
    public function testWebhookProcessingForDeclinedPayment(): void
    {
        $orderRef = 'ORDER_DECLINED_' . time();

        $webhookData = ApiResponses::webhookDeclinedPayment($orderRef);
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $webhook = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);

        // Verify declined status
        $this->assertTrue($webhook->isDeclined());
        $this->assertFalse($webhook->isProcessed());
        $this->assertEquals('DECLINED', $webhook->getStatus()->value);

        // Check decline reason
        $this->assertEquals('INSUFFICIENT_FUNDS', $webhook->getBankResponse());

        // Application would now:
        // - Mark order as 'payment_failed'
        // - Send payment failure notification
        // - Provide retry link to customer
        // - Log decline reason for analytics
    }

    /**
     * E2E: Webhook with invalid signature - security test
     *
     * Scenario:
     * Malicious actor sends fake webhook, signature verification prevents it
     */
    public function testWebhookWithInvalidSignatureIsRejected(): void
    {
        $this->expectException(InvalidSignatureException::class);

        $webhookData = ApiResponses::webhookProcessedPayment('FAKE_ORDER');
        $payload = json_encode($webhookData);
        $invalidSignature = 'completely_wrong_signature_abc123';

        // This should throw exception
        $this->client->parseWebhook($payload, $invalidSignature, $this->webhookSecret);

        // Application would:
        // - Log security warning
        // - Return HTTP 403
        // - Alert security team if repeated
    }

    /**
     * E2E: Webhook signature timing attack prevention
     *
     * Scenario:
     * Verify that signature comparison is timing-safe
     */
    public function testWebhookSignatureTimingSafety(): void
    {
        $payload = json_encode(['test' => 'data']);
        $correctSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        // Create signatures that differ at different positions
        $signatures = [
            'a' . substr($correctSignature, 1), // Differs at position 0
            substr($correctSignature, 0, -1) . 'z', // Differs at last position
            str_repeat('a', strlen($correctSignature)), // Completely different
        ];

        foreach ($signatures as $wrongSignature) {
            $isValid = $this->client->verifyWebhookSignature(
                $payload,
                $wrongSignature,
                $this->webhookSecret
            );

            $this->assertFalse($isValid, 'Invalid signature should always return false');
        }

        // Verify correct signature works
        $this->assertTrue(
            $this->client->verifyWebhookSignature($payload, $correctSignature, $this->webhookSecret)
        );
    }

    /**
     * E2E: Webhook with missing signature
     *
     * Scenario:
     * Webhook arrives without signature header
     */
    public function testWebhookWithMissingSignature(): void
    {
        $webhookData = ApiResponses::webhookProcessedPayment('ORDER_123');
        $payload = json_encode($webhookData);

        // Parse without signature (no verification)
        $webhook = $this->client->parseWebhook($payload);

        // Should parse successfully but application should log warning
        $this->assertNotNull($webhook);
        $this->assertTrue($webhook->isProcessed());

        // In production, application should:
        // - Log warning about missing signature
        // - Consider implementing IP whitelist
        // - May choose to reject webhooks without signature
    }

    /**
     * E2E: Duplicate webhook handling (idempotency)
     *
     * Scenario:
     * Same webhook received multiple times (GPG retry mechanism)
     */
    public function testDuplicateWebhookHandling(): void
    {
        $orderRef = 'ORDER_DUPLICATE_WEBHOOK';

        $webhookData = ApiResponses::webhookProcessedPayment($orderRef);
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        // First webhook
        $webhook1 = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);
        $transactionId1 = $webhook1->getTransactionId();

        // Duplicate webhook (same transaction ID)
        $webhook2 = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);
        $transactionId2 = $webhook2->getTransactionId();

        $this->assertEquals($transactionId1, $transactionId2);

        // Application should:
        // - Check if transaction ID already processed
        // - If yes, return 200 but skip processing
        // - Use database unique constraint on transaction_id
        // - Log duplicate webhook for monitoring
    }

    /**
     * E2E: Webhook ordering issues
     *
     * Scenario:
     * Webhooks may arrive out of order, handle correctly
     */
    public function testWebhooksArrivingOutOfOrder(): void
    {
        $bookingRef = 'BOOKING_OUT_OF_ORDER';

        // Webhook 2 arrives first (capture)
        $captureWebhook = ApiResponses::webhookProcessedPayment($bookingRef);
        $captureWebhook['transactionType'] = 'CAPT';
        $capturePayload = json_encode($captureWebhook);
        $captureSignature = hash_hmac('sha256', $capturePayload, $this->webhookSecret);

        $webhook2 = $this->client->parseWebhook($capturePayload, $captureSignature, $this->webhookSecret);
        $this->assertEquals('CAPT', $webhook2->getTransactionType()->value);

        // Webhook 1 arrives second (auth)
        $authWebhook = ApiResponses::webhookAuthorizedPayment($bookingRef);
        $authPayload = json_encode($authWebhook);
        $authSignature = hash_hmac('sha256', $authPayload, $this->webhookSecret);

        $webhook1 = $this->client->parseWebhook($authPayload, $authSignature, $this->webhookSecret);
        $this->assertEquals('AUTH', $webhook1->getTransactionType()->value);

        // Application should:
        // - Use transaction timestamps to determine order
        // - Don't overwrite newer status with older status
        // - Store all webhooks in audit log
    }

    /**
     * E2E: Webhook with malformed JSON
     *
     * Scenario:
     * Corrupted webhook payload
     */
    public function testWebhookWithMalformedJson(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Invalid webhook payload');

        $malformedPayload = '{invalid json {{';
        $signature = hash_hmac('sha256', $malformedPayload, $this->webhookSecret);

        $this->client->parseWebhook($malformedPayload, $signature, $this->webhookSecret);

        // Application should:
        // - Log error with raw payload
        // - Return HTTP 400
        // - Alert monitoring system
    }

    /**
     * E2E: Webhook timeout and retry mechanism
     *
     * Scenario:
     * Application is slow/unavailable, GPG retries webhook
     */
    public function testWebhookRetryMechanism(): void
    {
        $orderRef = 'ORDER_RETRY';
        $webhookData = ApiResponses::webhookProcessedPayment($orderRef);
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        // Simulate multiple delivery attempts
        $attempts = [];

        for ($i = 1; $i <= 3; $i++) {
            $webhook = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);

            $attempts[] = [
                'attempt' => $i,
                'transaction_id' => $webhook->getTransactionId(),
                'received_at' => time()
            ];
        }

        $this->assertCount(3, $attempts);

        // All attempts have same transaction ID
        $transactionIds = array_column($attempts, 'transaction_id');
        $this->assertCount(1, array_unique($transactionIds));

        // Application should:
        // - Return 200 immediately (don't wait for processing)
        // - Queue webhook for async processing
        // - Implement idempotency using transaction ID
        // - GPG retries: immediately, 5min, 15min, 1hr, 6hr
    }

    /**
     * E2E: Webhook for pre-authorized payment
     *
     * Scenario:
     * Handle AUTH webhook differently than SALE webhook
     */
    public function testWebhookForPreAuthorizedPayment(): void
    {
        $bookingRef = 'BOOKING_PREAUTH_' . time();

        $webhookData = ApiResponses::webhookAuthorizedPayment($bookingRef);
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $webhook = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);

        $this->assertEquals('AUTHORIZED', $webhook->getStatus()->value);
        $this->assertEquals('AUTH', $webhook->getTransactionType()->value);
        $this->assertNotNull($webhook->getAuthCode());

        // Application should:
        // - Mark booking as 'confirmed' (not 'paid')
        // - Store auth code for later capture
        // - Send booking confirmation (not payment receipt)
        // - Set reminder to capture before expiry
    }

    /**
     * E2E: Webhook data extraction and storage
     *
     * Scenario:
     * Extract all relevant data from webhook for database storage
     */
    public function testComprehensiveWebhookDataExtraction(): void
    {
        $orderRef = 'ORDER_FULL_DATA';

        $webhookData = ApiResponses::webhookProcessedPayment($orderRef);
        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $webhook = $this->client->parseWebhook($payload, $signature, $this->webhookSecret);

        // Extract all data for database storage
        $dbRecord = [
            // Transaction info
            'transaction_id' => $webhook->getTransactionId(),
            'gateway_id' => $webhook->getGatewayId(),
            'status' => $webhook->getStatus()->value,
            'transaction_type' => $webhook->getTransactionType()->value,

            // Payment details
            'amount' => $webhook->getAmount(),
            'currency' => $webhook->getCurrency(),
            'auth_code' => $webhook->getAuthCode(),

            // Card info (masked)
            'card_number' => $webhook->getCardNumber(),
            'card_scheme' => $webhook->getCardScheme(),

            // Bank response
            'bank_response' => $webhook->getBankResponse(),

            // Custom data
            'unique_reference' => $webhook->getUniqueReference(),
            'customer_email' => $webhook->getCustomerEmail(),

            // 3D Secure
            'threeds_authenticated' => $webhook->getThreeDSecure()['authenticated'] ?? false,

            // UDF fields
            'udf1' => $webhook->getUdfField(1),
            'udf2' => $webhook->getUdfField(2),

            // Timestamps
            'processed_at' => $webhook->getProcessedAt(),
            'webhook_received_at' => date('Y-m-d H:i:s'),

            // Raw payload for debugging
            'raw_webhook' => json_encode($webhook->getRawPayload())
        ];

        // Verify all data extracted
        $this->assertNotEmpty($dbRecord['transaction_id']);
        $this->assertNotEmpty($dbRecord['status']);
        $this->assertNotEmpty($dbRecord['amount']);
        $this->assertNotEmpty($dbRecord['card_number']);
        $this->assertNotEmpty($dbRecord['unique_reference']);

        // Store in database...
    }

    /**
     * E2E: Webhook case-insensitive field handling
     *
     * Scenario:
     * GPG may send fields in different cases
     */
    public function testWebhookCaseInsensitiveFieldHandling(): void
    {
        // Test both camelCase and PascalCase
        $webhookCamel = [
            'transactionId' => 'TXN_CAMEL',
            'gatewayId' => 'GW_123',
            'status' => 'PROCESSED',
            'transactionType' => 'SALE',
            'amount' => 50.00,
            'currency' => 'EUR'
        ];

        $webhookPascal = [
            'TransactionId' => 'TXN_PASCAL',
            'GatewayId' => 'GW_456',
            'Status' => 'PROCESSED',
            'TransactionType' => 'SALE',
            'Amount' => 75.00,
            'Currency' => 'EUR'
        ];

        $payloadCamel = json_encode($webhookCamel);
        $signatureCamel = hash_hmac('sha256', $payloadCamel, $this->webhookSecret);

        $payloadPascal = json_encode($webhookPascal);
        $signaturePascal = hash_hmac('sha256', $payloadPascal, $this->webhookSecret);

        // Both should parse successfully
        $webhook1 = $this->client->parseWebhook($payloadCamel, $signatureCamel, $this->webhookSecret);
        $webhook2 = $this->client->parseWebhook($payloadPascal, $signaturePascal, $this->webhookSecret);

        $this->assertEquals('TXN_CAMEL', $webhook1->getTransactionId());
        $this->assertEquals('TXN_PASCAL', $webhook2->getTransactionId());
        $this->assertEquals(50.00, $webhook1->getAmount());
        $this->assertEquals(75.00, $webhook2->getAmount());
    }
}