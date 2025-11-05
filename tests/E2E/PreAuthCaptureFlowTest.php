<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\E2E;

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;
use BornMT\MitaGpg\Tests\Helpers\MockHttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * E2E Test: Pre-Authorization and Capture Flow
 *
 * Tests the two-step payment process used for hotels, car rentals, etc.
 */
class PreAuthCaptureFlowTest extends TestCase
{
    private function createClientWithMockHttp($mockClient): GpgClient
    {
        $client = new GpgClient(
            apiKey: 'test_api_key',
            testMode: true
        );

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $mockClient);

        return $client;
    }

    /**
     * E2E: Complete hotel reservation flow
     *
     * Scenario:
     * 1. Guest makes reservation - pre-authorize €150
     * 2. Guest checks in - capture full amount
     * 3. Payment settled to merchant
     */
    public function testHotelReservationWithPreAuthAndCapture(): void
    {
        $bookingRef = 'BOOKING_' . time();
        $authTransactionId = 'TXN_AUTH_12345';

        // Step 1: Pre-authorize payment at booking time
        $mockClientAuth = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($authTransactionId)
        );

        $clientAuth = $this->createClientWithMockHttp($mockClientAuth);

        $authRequest = new PaymentRequest(
            amount: 150.00,
            uniqueReference: $bookingRef,
            transactionType: TransactionType::AUTH, // Pre-authorization
            customerEmail: 'guest@hotel.com',
            customerFirstName: 'Maria',
            customerLastName: 'Garcia',
            description: 'Hotel Reservation - Deluxe Room'
        );

        $authRequest->setUdfField(1, 'Room Type: Deluxe')
                    ->setUdfField(2, 'Check-in: 2025-06-01')
                    ->setUdfField(3, 'Check-out: 2025-06-05')
                    ->setUdfField(4, 'Nights: 4');

        $authResponse = $clientAuth->createPayment($authRequest);

        $this->assertTrue($authResponse->isSuccess());
        $this->assertEquals($authTransactionId, $authResponse->getTransactionId());

        // Guest redirected to payment page, completes 3D Secure...
        $paymentUrl = $clientAuth->buildPaymentPageUrl($authTransactionId);
        $this->assertStringContainsString($authTransactionId, $paymentUrl);

        // Simulate webhook: Pre-authorization successful
        $webhookData = ApiResponses::webhookAuthorizedPayment($bookingRef);
        $webhookPayload = json_encode($webhookData);
        $webhookSecret = 'test_secret';
        $webhookSignature = hash_hmac('sha256', $webhookPayload, $webhookSecret);

        $webhook = $clientAuth->parseWebhook($webhookPayload, $webhookSignature, $webhookSecret);

        $this->assertEquals('AUTHORIZED', $webhook->getStatus()->value);
        $this->assertEquals(150.00, $webhook->getAmount());
        $this->assertNotNull($webhook->getAuthCode());

        // At this point: Funds are held, booking confirmed, room reserved

        // Step 2: Guest checks in - capture the payment
        $mockClientCapture = MockHttpClient::withResponse(
            ApiResponses::successfulCapture($authTransactionId)
        );

        $clientCapture = $this->createClientWithMockHttp($mockClientCapture);

        $captureResponse = $clientCapture->capturePayment(
            transactionId: $authTransactionId,
            amount: 150.00 // Full amount
        );

        $this->assertTrue($captureResponse->isSuccess());
        $this->assertEquals('PROCESSED', $captureResponse->getStatus()->value);

        // Payment now settled to merchant account
    }

    /**
     * E2E: Hotel reservation with additional charges at check-in
     *
     * Scenario:
     * 1. Pre-authorize €150 for room
     * 2. Guest uses minibar and room service (€30)
     * 3. Capture €180 at checkout
     */
    public function testPreAuthWithAdditionalChargesAtCapture(): void
    {
        $bookingRef = 'BOOKING_EXTRAS_' . time();
        $transactionId = 'TXN_AUTH_EXTRAS';

        // Pre-authorize original amount
        $mockClientAuth = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($transactionId)
        );

        $clientAuth = $this->createClientWithMockHttp($mockClientAuth);

        $authRequest = new PaymentRequest(
            amount: 150.00,
            uniqueReference: $bookingRef,
            transactionType: TransactionType::AUTH
        );

        $authResponse = $clientAuth->createPayment($authRequest);
        $this->assertTrue($authResponse->isSuccess());

        // Capture with additional charges
        $captureData = ApiResponses::successfulCapture($transactionId);
        $captureData['result']['amount'] = 180.00; // Original + extras

        $mockClientCapture = MockHttpClient::withResponse($captureData);
        $clientCapture = $this->createClientWithMockHttp($mockClientCapture);

        $captureResponse = $clientCapture->capturePayment(
            transactionId: $transactionId,
            amount: 180.00, // Room (150) + Extras (30)
            uniqueReference: $bookingRef . '_CHECKOUT'
        );

        $this->assertTrue($captureResponse->isSuccess());
        $this->assertEquals('PROCESSED', $captureResponse->getStatus()->value);

        // Verify captured amount is higher than original pre-auth
        // (This is allowed up to a certain percentage above the pre-auth amount)
    }

    /**
     * E2E: Partial capture scenario
     *
     * Scenario:
     * 1. Pre-authorize €200 (estimate)
     * 2. Actual charge is only €150
     * 3. Capture partial amount, release rest
     */
    public function testPartialCaptureWithRemainingFundsReleased(): void
    {
        $orderRef = 'ORDER_PARTIAL_' . time();
        $transactionId = 'TXN_PARTIAL_12345';

        // Pre-authorize estimated amount
        $mockClientAuth = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($transactionId)
        );

        $clientAuth = $this->createClientWithMockHttp($mockClientAuth);

        $authRequest = new PaymentRequest(
            amount: 200.00,
            uniqueReference: $orderRef,
            transactionType: TransactionType::AUTH,
            description: 'Rental Service - Estimated'
        );

        $authResponse = $clientAuth->createPayment($authRequest);
        $this->assertTrue($authResponse->isSuccess());

        // Capture only partial amount
        $partialCaptureData = ApiResponses::successfulCapture($transactionId);
        $partialCaptureData['result']['amount'] = 150.00;

        $mockClientCapture = MockHttpClient::withResponse($partialCaptureData);
        $clientCapture = $this->createClientWithMockHttp($mockClientCapture);

        $captureResponse = $clientCapture->capturePayment(
            transactionId: $transactionId,
            amount: 150.00 // Partial capture
        );

        $this->assertTrue($captureResponse->isSuccess());

        // Remaining €50 is released back to customer's card
    }

    /**
     * E2E: Cancelled reservation - void pre-authorization
     *
     * Scenario:
     * 1. Guest makes reservation - pre-authorize
     * 2. Guest cancels before check-in
     * 3. Void the pre-authorization (release funds)
     */
    public function testCancelledReservationVoidPreAuth(): void
    {
        $bookingRef = 'BOOKING_CANCELLED_' . time();
        $transactionId = 'TXN_VOID_12345';

        // Pre-authorize
        $mockClientAuth = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($transactionId)
        );

        $clientAuth = $this->createClientWithMockHttp($mockClientAuth);

        $authRequest = new PaymentRequest(
            amount: 200.00,
            uniqueReference: $bookingRef,
            transactionType: TransactionType::AUTH
        );

        $authResponse = $clientAuth->createPayment($authRequest);
        $this->assertTrue($authResponse->isSuccess());

        // Guest cancels - void the pre-authorization
        $voidData = [
            'success' => true,
            'result' => [
                'transactionId' => $transactionId,
                'status' => 'CANCELLED',
                'amount' => 200.00,
                'voidedAt' => date('Y-m-d\TH:i:s\Z')
            ],
            'processId' => 'PROC_VOID',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClientVoid = MockHttpClient::withResponse($voidData);
        $clientVoid = $this->createClientWithMockHttp($mockClientVoid);

        $voidResponse = $clientVoid->voidPayment($transactionId);

        $this->assertTrue($voidResponse->isSuccess());

        // Funds immediately released, no charge to customer
    }

    /**
     * E2E: Car rental with deposit hold
     *
     * Scenario:
     * 1. Pre-authorize €500 (rental + deposit)
     * 2. Customer returns car undamaged
     * 3. Capture only rental amount (€300)
     * 4. Deposit (€200) released automatically
     */
    public function testCarRentalWithDepositHold(): void
    {
        $rentalRef = 'RENTAL_' . time();
        $transactionId = 'TXN_RENTAL_12345';

        // Pre-authorize rental + deposit
        $mockClientAuth = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation($transactionId)
        );

        $clientAuth = $this->createClientWithMockHttp($mockClientAuth);

        $authRequest = new PaymentRequest(
            amount: 500.00, // €300 rental + €200 deposit
            uniqueReference: $rentalRef,
            transactionType: TransactionType::AUTH,
            description: 'Car Rental - 5 days + Deposit'
        );

        $authRequest->setUdfField(1, 'Rental Days: 5')
                    ->setUdfField(2, 'Vehicle: Toyota Corolla')
                    ->setUdfField(3, 'Deposit: 200 EUR');

        $authResponse = $clientAuth->createPayment($authRequest);
        $this->assertTrue($authResponse->isSuccess());

        // Customer returns car - capture only rental amount
        $captureData = ApiResponses::successfulCapture($transactionId);
        $captureData['result']['amount'] = 300.00; // Rental only

        $mockClientCapture = MockHttpClient::withResponse($captureData);
        $clientCapture = $this->createClientWithMockHttp($mockClientCapture);

        $captureResponse = $clientCapture->capturePayment(
            transactionId: $transactionId,
            amount: 300.00 // Capture rental only, release deposit
        );

        $this->assertTrue($captureResponse->isSuccess());

        // €200 deposit released, €300 charged
    }

    /**
     * E2E: Multiple captures not allowed
     *
     * Scenario:
     * Verify that you can only capture once per pre-authorization
     */
    public function testCannotCapturePreAuthMultipleTimes(): void
    {
        $transactionId = 'TXN_ALREADY_CAPTURED';

        // First capture succeeds
        $captureData1 = ApiResponses::successfulCapture($transactionId);

        // Second capture fails - already captured
        $captureData2 = [
            'success' => false,
            'message' => 'Transaction has already been captured',
            'errorCode' => 'ALREADY_CAPTURED',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = (new MockHttpClient())
            ->addJsonResponse($captureData1)
            ->addJsonResponse($captureData2, 400)
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        // First capture
        $response1 = $client->capturePayment($transactionId, 100.00);
        $this->assertTrue($response1->isSuccess());

        // Second capture attempt should fail
        try {
            $response2 = $client->capturePayment($transactionId, 50.00);
            $this->fail('Expected exception for duplicate capture');
        } catch (\Exception $e) {
            $this->assertStringContainsString('already been captured', $e->getMessage());
        }
    }

    /**
     * E2E: Pre-auth expiration handling
     *
     * Scenario:
     * Pre-authorization expires if not captured within time limit
     */
    public function testPreAuthExpirationAfterTimeLimit(): void
    {
        $transactionId = 'TXN_EXPIRED';

        // Attempt to capture expired pre-auth
        $expiredData = [
            'success' => false,
            'message' => 'Pre-authorization has expired',
            'errorCode' => 'AUTH_EXPIRED',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withResponse($expiredData, 400);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            $client->capturePayment($transactionId, 100.00);
            $this->fail('Expected exception for expired pre-auth');
        } catch (\Exception $e) {
            $this->assertStringContainsString('expired', $e->getMessage());
        }

        // Application should handle by creating new payment request
    }
}