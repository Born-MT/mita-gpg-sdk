<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests;

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\DTO\PaymentResponse;
use BornMT\MitaGpg\DTO\WebhookPayload;
use BornMT\MitaGpg\Enums\TransactionType;
use BornMT\MitaGpg\Enums\TransactionStatus;
use BornMT\MitaGpg\Exceptions\InvalidSignatureException;
use PHPUnit\Framework\TestCase;

class GpgClientTest extends TestCase
{
    private GpgClient $client;

    protected function setUp(): void
    {
        $this->client = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'] ?? 'test_api_key',
            testMode: true
        );
    }

    public function testClientInstantiation(): void
    {
        $this->assertInstanceOf(GpgClient::class, $this->client);
        $this->assertTrue($this->client->isTestMode());
        $this->assertEquals($_ENV['GPG_API_KEY'] ?? 'test_api_key', $this->client->getApiKey());
    }

    public function testBuildPaymentPageUrl(): void
    {
        $transactionId = 'test_transaction_123';
        $expectedUrl = 'https://gpg.apcopay.com/pay/test_transaction_123';

        $url = $this->client->buildPaymentPageUrl($transactionId);

        $this->assertEquals($expectedUrl, $url);
    }

    public function testVerifyWebhookSignature(): void
    {
        $payload = json_encode([
            'transactionId' => 'test123',
            'status' => 'PROCESSED',
            'amount' => 50.00
        ]);

        $secret = 'test_webhook_secret';
        $validSignature = hash_hmac('sha256', $payload, $secret);
        $invalidSignature = 'invalid_signature_abc123';

        // Valid signature should return true
        $this->assertTrue(
            $this->client->verifyWebhookSignature($payload, $validSignature, $secret)
        );

        // Invalid signature should return false
        $this->assertFalse(
            $this->client->verifyWebhookSignature($payload, $invalidSignature, $secret)
        );
    }

    public function testParseWebhookWithValidSignature(): void
    {
        $payloadData = [
            'transactionId' => 'TXN_123456',
            'gatewayId' => 'GW_789',
            'status' => 'PROCESSED',
            'transactionType' => 'SALE',
            'amount' => 100.50,
            'currency' => 'EUR',
            'authCode' => 'AUTH123',
            'cardNumber' => '4111****1111',
            'cardScheme' => 'VISA',
            'uniqueReference' => 'ORDER_12345'
        ];

        $payload = json_encode($payloadData);
        $secret = 'webhook_secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $webhook = $this->client->parseWebhook($payload, $signature, $secret);

        $this->assertInstanceOf(WebhookPayload::class, $webhook);
        $this->assertEquals('TXN_123456', $webhook->getTransactionId());
        $this->assertEquals('GW_789', $webhook->getGatewayId());
        $this->assertEquals(TransactionStatus::PROCESSED, $webhook->getStatus());
        $this->assertEquals(TransactionType::SALE, $webhook->getTransactionType());
        $this->assertEquals(100.50, $webhook->getAmount());
        $this->assertTrue($webhook->isProcessed());
        $this->assertFalse($webhook->isDeclined());
    }

    public function testParseWebhookWithInvalidSignatureThrowsException(): void
    {
        $this->expectException(InvalidSignatureException::class);

        $payload = json_encode(['test' => 'data']);
        $invalidSignature = 'invalid';
        $secret = 'secret';

        $this->client->parseWebhook($payload, $invalidSignature, $secret);
    }

    public function testParseWebhookWithoutSignatureVerification(): void
    {
        $payloadData = [
            'transactionId' => 'TXN_999',
            'gatewayId' => 'GW_999',
            'status' => 'DECLINED',
            'transactionType' => 'SALE',
            'amount' => 25.00,
            'currency' => 'EUR'
        ];

        $payload = json_encode($payloadData);

        // Parse without signature verification
        $webhook = $this->client->parseWebhook($payload);

        $this->assertInstanceOf(WebhookPayload::class, $webhook);
        $this->assertEquals('TXN_999', $webhook->getTransactionId());
        $this->assertTrue($webhook->isDeclined());
        $this->assertFalse($webhook->isProcessed());
    }

    public function testPaymentRequestToArray(): void
    {
        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'TEST_123',
            transactionType: TransactionType::SALE,
            customerEmail: 'test@example.com',
            customerFirstName: 'John',
            customerLastName: 'Doe',
            description: 'Test Payment'
        );

        $array = $request->toArray();

        $this->assertEquals('50.00', $array['Amount']);
        $this->assertEquals('TEST_123', $array['UniqueReference']);
        $this->assertEquals('SALE', $array['TransactionType']);
        $this->assertEquals('test@example.com', $array['CustomerEmail']);
        $this->assertEquals('John', $array['CustomerFirstName']);
        $this->assertEquals('Doe', $array['CustomerLastName']);
        $this->assertEquals('Test Payment', $array['Description']);
    }

    public function testPaymentRequestWithUdfFields(): void
    {
        $request = new PaymentRequest(
            amount: 100.00,
            uniqueReference: 'TEST_UDF'
        );

        $request->setUdfField(1, 'Order ID: 123')
                ->setUdfField(2, 'Customer ID: 456')
                ->setUdfField(5, 'Campaign: SUMMER2025');

        $array = $request->toArray();

        $this->assertEquals('Order ID: 123', $array['UDF1']);
        $this->assertEquals('Customer ID: 456', $array['UDF2']);
        $this->assertEquals('Campaign: SUMMER2025', $array['UDF5']);
        $this->assertArrayNotHasKey('UDF3', $array);
        $this->assertArrayNotHasKey('UDF4', $array);
    }

    public function testPaymentRequestFluentInterface(): void
    {
        $request = new PaymentRequest(
            amount: 75.00,
            uniqueReference: 'FLUENT_TEST'
        );

        $result = $request
            ->setCustomerEmail('fluent@test.com')
            ->setCustomerName('Jane', 'Smith')
            ->setDescription('Fluent test')
            ->addMetadata('custom_field', 'custom_value');

        // Fluent interface should return same instance
        $this->assertSame($request, $result);

        $array = $request->toArray();
        $this->assertEquals('fluent@test.com', $array['CustomerEmail']);
        $this->assertEquals('Jane', $array['CustomerFirstName']);
        $this->assertEquals('Smith', $array['CustomerLastName']);
        $this->assertEquals('Fluent test', $array['Description']);
        $this->assertEquals('custom_value', $array['custom_field']);
    }

    public function testPaymentResponseFromArray(): void
    {
        $data = [
            'success' => true,
            'result' => [
                'transactionId' => 'TXN_ABC',
                'gatewayId' => 'GW_XYZ',
                'status' => 'PENDING',
                'paymentUrl' => 'https://gpg.apcopay.com/pay/TXN_ABC'
            ],
            'processId' => 'PROC_123',
            'dateTime' => '2025-01-05T10:30:00Z'
        ];

        $response = PaymentResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('TXN_ABC', $response->getTransactionId());
        $this->assertEquals('GW_XYZ', $response->getGatewayId());
        $this->assertEquals(TransactionStatus::PENDING, $response->getStatus());
        $this->assertEquals('https://gpg.apcopay.com/pay/TXN_ABC', $response->getPaymentUrl());
        $this->assertEquals('PROC_123', $response->getProcessId());
    }

    public function testWebhookPayloadHelperMethods(): void
    {
        $processedPayload = WebhookPayload::fromArray([
            'transactionId' => 'TXN_1',
            'gatewayId' => 'GW_1',
            'status' => 'PROCESSED',
            'transactionType' => 'SALE',
            'amount' => 50.00,
            'currency' => 'EUR'
        ]);

        $this->assertTrue($processedPayload->isProcessed());
        $this->assertFalse($processedPayload->isDeclined());
        $this->assertFalse($processedPayload->isPending());

        $declinedPayload = WebhookPayload::fromArray([
            'transactionId' => 'TXN_2',
            'gatewayId' => 'GW_2',
            'status' => 'DECLINED',
            'transactionType' => 'SALE',
            'amount' => 30.00,
            'currency' => 'EUR'
        ]);

        $this->assertTrue($declinedPayload->isDeclined());
        $this->assertFalse($declinedPayload->isProcessed());
        $this->assertFalse($declinedPayload->isPending());
    }

    public function testTransactionTypes(): void
    {
        $this->assertEquals('SALE', TransactionType::SALE->value);
        $this->assertEquals('AUTH', TransactionType::AUTH->value);
        $this->assertEquals('CAPT', TransactionType::CAPTURE->value);
        $this->assertEquals('REFUND', TransactionType::REFUND->value);
        $this->assertEquals('VOID', TransactionType::VOID->value);
    }

    public function testTransactionStatuses(): void
    {
        $this->assertEquals('PENDING', TransactionStatus::PENDING->value);
        $this->assertEquals('PROCESSED', TransactionStatus::PROCESSED->value);
        $this->assertEquals('DECLINED', TransactionStatus::DECLINED->value);
        $this->assertEquals('AUTHORIZED', TransactionStatus::AUTHORIZED->value);
        $this->assertEquals('REFUNDED', TransactionStatus::REFUNDED->value);
    }
}
