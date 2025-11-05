<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\E2E;

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;
use BornMT\MitaGpg\Enums\TransactionStatus;
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;
use BornMT\MitaGpg\Tests\Helpers\MockHttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * E2E Test: Complete Payment Flow
 *
 * This test simulates the complete payment lifecycle from creation to confirmation
 * without making actual API calls.
 */
class PaymentFlowTest extends TestCase
{
    private function createClientWithMockHttp($mockClient): GpgClient
    {
        $client = new GpgClient(
            apiKey: 'test_api_key',
            testMode: true
        );

        // Use reflection to inject mock HTTP client
        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $mockClient);

        return $client;
    }

    /**
     * E2E: Complete successful payment flow
     *
     * Scenario:
     * 1. Customer initiates checkout
     * 2. Create payment request
     * 3. Redirect customer to payment page
     * 4. Customer completes payment
     * 5. Receive webhook confirmation
     * 6. Query transaction details
     */
    public function testCompleteSuccessfulPaymentFlow(): void
    {
        $orderRef = 'ORDER_E2E_' . time();
        $transactionId = 'TXN_E2E_12345';

        // Step 1: Create payment
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(ApiResponses::successfulPaymentCreation($transactionId))
            ->addJsonResponse(ApiResponses::transactionDetails($transactionId, 'PROCESSED'))
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        // Create payment request
        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: $orderRef,
            transactionType: TransactionType::SALE,
            customerEmail: 'customer@example.com',
            customerFirstName: 'John',
            customerLastName: 'Doe',
            description: 'E2E Test Order',
            redirectUrl: 'https://example.com/success',
            callbackUrl: 'https://example.com/webhook'
        );

        $request->setUdfField(1, "Order Ref: {$orderRef}")
                ->setUdfField(2, 'Customer ID: 123');

        $response = $client->createPayment($request);

        // Assert payment created successfully
        $this->assertTrue($response->isSuccess());
        $this->assertEquals($transactionId, $response->getTransactionId());
        $this->assertEquals(TransactionStatus::PENDING, $response->getStatus());

        // Step 2: Build payment URL (customer would be redirected here)
        $paymentUrl = $client->buildPaymentPageUrl($transactionId);
        $this->assertEquals(
            'https://gpg.apcopay.com/pay/' . $transactionId,
            $paymentUrl
        );

        // Step 3: Simulate webhook callback (payment completed)
        $webhookData = ApiResponses::webhookProcessedPayment($orderRef);
        $webhookPayload = json_encode($webhookData);
        $webhookSecret = 'test_webhook_secret';
        $webhookSignature = hash_hmac('sha256', $webhookPayload, $webhookSecret);

        // Verify and parse webhook
        $this->assertTrue(
            $client->verifyWebhookSignature($webhookPayload, $webhookSignature, $webhookSecret)
        );

        $webhook = $client->parseWebhook($webhookPayload, $webhookSignature, $webhookSecret);

        $this->assertTrue($webhook->isProcessed());
        $this->assertEquals('PROCESSED', $webhook->getStatus()->value);
        $this->assertEquals(50.00, $webhook->getAmount());
        $this->assertEquals($orderRef, $webhook->getUniqueReference());
        $this->assertEquals('VISA', $webhook->getCardScheme());

        // Step 4: Query transaction details for confirmation
        $transaction = $client->getTransaction($transactionId);

        $this->assertTrue($transaction['success']);
        $this->assertEquals($transactionId, $transaction['result']['transactionId']);
        $this->assertEquals('PROCESSED', $transaction['result']['status']);
        $this->assertEquals(50.00, $transaction['result']['amount']);

        // Verify 3D Secure was used
        $this->assertTrue($transaction['result']['threeDSecure']['authenticated']);
    }

    /**
     * E2E: Payment flow with customer abandonment
     *
     * Scenario:
     * 1. Create payment
     * 2. Customer redirected to payment page
     * 3. Customer abandons payment (closes browser)
     * 4. Transaction remains in PENDING state
     */
    public function testPaymentFlowWithCustomerAbandonment(): void
    {
        $orderRef = 'ORDER_ABANDONED_' . time();
        $transactionId = 'TXN_ABANDONED_12345';

        $mockClient = (new MockHttpClient())
            ->addJsonResponse(ApiResponses::successfulPaymentCreation($transactionId))
            ->addJsonResponse(ApiResponses::transactionDetails($transactionId, 'PENDING'))
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        // Create payment
        $request = new PaymentRequest(
            amount: 75.00,
            uniqueReference: $orderRef
        );

        $response = $client->createPayment($request);
        $this->assertTrue($response->isSuccess());

        // Simulate checking transaction status after some time
        $transaction = $client->getTransaction($transactionId);

        $this->assertEquals('PENDING', $transaction['result']['status']);

        // No webhook received (customer abandoned)
        // Application should handle timeout/expiry
    }

    /**
     * E2E: Payment flow with card decline
     *
     * Scenario:
     * 1. Create payment
     * 2. Customer enters card details
     * 3. Card is declined by bank
     * 4. Receive decline webhook
     * 5. Show error to customer
     */
    public function testPaymentFlowWithCardDecline(): void
    {
        $orderRef = 'ORDER_DECLINED_' . time();
        $transactionId = 'TXN_DECLINED_12345';

        $mockClient = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($transactionId)
        );

        $client = $this->createClientWithMockHttp($mockClient);

        // Create payment
        $request = new PaymentRequest(
            amount: 100.00,
            uniqueReference: $orderRef,
            customerEmail: 'declined@example.com'
        );

        $response = $client->createPayment($request);
        $this->assertTrue($response->isSuccess());

        // Simulate declined webhook
        $webhookData = ApiResponses::webhookDeclinedPayment($orderRef);
        $webhookPayload = json_encode($webhookData);
        $webhookSecret = 'test_secret';
        $webhookSignature = hash_hmac('sha256', $webhookPayload, $webhookSecret);

        $webhook = $client->parseWebhook($webhookPayload, $webhookSignature, $webhookSecret);

        $this->assertTrue($webhook->isDeclined());
        $this->assertFalse($webhook->isProcessed());
        $this->assertEquals('DECLINED', $webhook->getStatus()->value);
        $this->assertEquals('INSUFFICIENT_FUNDS', $webhook->getBankResponse());

        // Application should:
        // - Mark order as failed
        // - Show decline reason to customer
        // - Allow retry with different card
    }

    /**
     * E2E: Multiple payments in sequence (shopping session)
     *
     * Scenario:
     * Customer makes multiple purchases in one session
     */
    public function testMultiplePaymentsInSequence(): void
    {
        $payments = [];

        for ($i = 1; $i <= 3; $i++) {
            $orderRef = "ORDER_MULTI_{$i}_" . time();
            $transactionId = "TXN_MULTI_{$i}";

            $mockClient = MockHttpClient::withResponse(
                ApiResponses::successfulPaymentCreation($transactionId)
            );

            $client = $this->createClientWithMockHttp($mockClient);

            $request = new PaymentRequest(
                amount: 25.00 * $i,
                uniqueReference: $orderRef,
                description: "Purchase #{$i}"
            );

            $response = $client->createPayment($request);

            $this->assertTrue($response->isSuccess());
            $this->assertEquals($transactionId, $response->getTransactionId());

            $payments[] = [
                'order_ref' => $orderRef,
                'transaction_id' => $transactionId,
                'amount' => 25.00 * $i
            ];
        }

        // Verify all payments were created
        $this->assertCount(3, $payments);
        $this->assertEquals(25.00, $payments[0]['amount']);
        $this->assertEquals(50.00, $payments[1]['amount']);
        $this->assertEquals(75.00, $payments[2]['amount']);
    }

    /**
     * E2E: Payment with custom metadata and UDF fields
     *
     * Scenario:
     * E-commerce order with extensive metadata
     */
    public function testPaymentWithExtensiveMetadata(): void
    {
        $orderRef = 'ORDER_METADATA_' . time();
        $transactionId = 'TXN_METADATA_12345';

        $mockClient = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($transactionId)
        );

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 199.99,
            uniqueReference: $orderRef,
            customerEmail: 'premium@customer.com',
            customerFirstName: 'Jane',
            customerLastName: 'Smith',
            customerPhone: '+356 21234567',
            description: 'Premium Package Purchase'
        );

        // Add extensive metadata
        $request->setUdfField(1, 'Order ID: 98765')
                ->setUdfField(2, 'Customer ID: CUS_12345')
                ->setUdfField(3, 'Package: Premium Annual')
                ->setUdfField(4, 'Promo Code: SUMMER2025')
                ->setUdfField(5, 'Sales Rep: SR_789')
                ->addMetadata('shipping_address', '123 Main St, Valletta, Malta')
                ->addMetadata('gift_message', 'Happy Birthday!')
                ->addMetadata('newsletter_signup', 'true');

        $response = $client->createPayment($request);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals($transactionId, $response->getTransactionId());

        // Verify request was built with all metadata
        $requestData = $request->toArray();
        $this->assertEquals('Order ID: 98765', $requestData['UDF1']);
        $this->assertEquals('Customer ID: CUS_12345', $requestData['UDF2']);
        $this->assertEquals('Package: Premium Annual', $requestData['UDF3']);
        $this->assertEquals('Promo Code: SUMMER2025', $requestData['UDF4']);
        $this->assertEquals('Sales Rep: SR_789', $requestData['UDF5']);
        $this->assertEquals('123 Main St, Valletta, Malta', $requestData['shipping_address']);
    }

    /**
     * E2E: Get transaction history
     *
     * Scenario:
     * Query recent transactions for reporting
     */
    public function testGetTransactionHistory(): void
    {
        $mockClient = MockHttpClient::withResponse(
            ApiResponses::transactionList()
        );

        $client = $this->createClientWithMockHttp($mockClient);

        $filters = [
            'startDate' => date('Y-m-d', strtotime('-7 days')),
            'endDate' => date('Y-m-d'),
            'status' => 'PROCESSED',
            'pageSize' => 50,
            'pageNumber' => 1
        ];

        $result = $client->getTransactions($filters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('transactions', $result['result']);
        $this->assertCount(3, $result['result']['transactions']);

        // Verify transaction data structure
        $firstTx = $result['result']['transactions'][0];
        $this->assertArrayHasKey('transactionId', $firstTx);
        $this->assertArrayHasKey('status', $firstTx);
        $this->assertArrayHasKey('amount', $firstTx);

        // Verify pagination info
        $this->assertEquals(3, $result['result']['totalCount']);
        $this->assertEquals(50, $result['result']['pageSize']);
        $this->assertEquals(1, $result['result']['pageNumber']);
    }
}