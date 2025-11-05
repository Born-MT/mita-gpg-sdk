<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\E2E;

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;
use BornMT\MitaGpg\Tests\Helpers\MockHttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * E2E Test: Refund Flow
 *
 * Tests various refund scenarios
 */
class RefundFlowTest extends TestCase
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
     * E2E: Full refund for customer return
     *
     * Scenario:
     * 1. Customer purchases product (€50)
     * 2. Customer returns product
     * 3. Process full refund
     * 4. Funds returned to customer's card
     */
    public function testFullRefundForCustomerReturn(): void
    {
        $transactionId = 'TXN_REFUND_FULL_12345';

        // Process full refund
        $mockClient = MockHttpClient::withResponse(
            ApiResponses::successfulRefund($transactionId)
        );

        $client = $this->createClientWithMockHttp($mockClient);

        $refundResponse = $client->refundPayment(
            transactionId: $transactionId
        );

        $this->assertTrue($refundResponse->isSuccess());
        $this->assertEquals('REFUNDED', $refundResponse->getStatus()->value);

        // Full amount refunded
        // Customer will see credit on their card statement within 5-10 business days
    }

    /**
     * E2E: Partial refund for damaged item
     *
     * Scenario:
     * 1. Customer purchased item for €100
     * 2. Item arrived damaged, offered 30% refund
     * 3. Process partial refund of €30
     * 4. Customer keeps item, gets partial refund
     */
    public function testPartialRefundForDamagedItem(): void
    {
        $transactionId = 'TXN_PARTIAL_REFUND';

        // Original transaction was €100
        // Refunding €30 (30%)
        $refundData = ApiResponses::successfulRefund($transactionId);
        $refundData['result']['amount'] = 30.00;

        $mockClient = MockHttpClient::withResponse($refundData);
        $client = $this->createClientWithMockHttp($mockClient);

        $refundResponse = $client->refundPayment(
            transactionId: $transactionId,
            amount: 30.00,
            uniqueReference: 'REFUND_PARTIAL_' . time()
        );

        $this->assertTrue($refundResponse->isSuccess());
        $this->assertEquals('REFUNDED', $refundResponse->getStatus()->value);

        // €30 refunded, €70 remains charged
    }

    /**
     * E2E: Multiple partial refunds
     *
     * Scenario:
     * 1. Order total: €200 (3 items)
     * 2. Return item 1: Refund €50
     * 3. Return item 2: Refund €75
     * 4. Keep item 3: Final charge €75
     */
    public function testMultiplePartialRefunds(): void
    {
        $transactionId = 'TXN_MULTI_REFUND';

        // First partial refund (€50)
        $refundData1 = ApiResponses::successfulRefund($transactionId);
        $refundData1['result']['amount'] = 50.00;

        // Second partial refund (€75)
        $refundData2 = ApiResponses::successfulRefund($transactionId);
        $refundData2['result']['amount'] = 75.00;

        $mockClient = (new MockHttpClient())
            ->addJsonResponse($refundData1)
            ->addJsonResponse($refundData2)
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        // First refund
        $refund1 = $client->refundPayment(
            transactionId: $transactionId,
            amount: 50.00,
            uniqueReference: 'REFUND_1_' . time()
        );

        $this->assertTrue($refund1->isSuccess());

        // Second refund
        $refund2 = $client->refundPayment(
            transactionId: $transactionId,
            amount: 75.00,
            uniqueReference: 'REFUND_2_' . time()
        );

        $this->assertTrue($refund2->isSuccess());

        // Total refunded: €125, remaining charge: €75
    }

    /**
     * E2E: Refund for cancelled event
     *
     * Scenario:
     * Event cancelled, all attendees get full refunds
     */
    public function testBulkRefundForCancelledEvent(): void
    {
        $attendeeTransactions = [
            'TXN_ATTENDEE_1',
            'TXN_ATTENDEE_2',
            'TXN_ATTENDEE_3',
            'TXN_ATTENDEE_4',
            'TXN_ATTENDEE_5'
        ];

        $responses = [];
        foreach ($attendeeTransactions as $txId) {
            $responses[] = ApiResponses::successfulRefund($txId);
        }

        $mockClient = (new MockHttpClient())
            ->addResponses($responses)
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        $refundResults = [];

        foreach ($attendeeTransactions as $txId) {
            $response = $client->refundPayment($txId);

            $refundResults[] = [
                'transaction_id' => $txId,
                'success' => $response->isSuccess(),
                'status' => $response->getStatus()->value
            ];
        }

        // Verify all refunds successful
        $this->assertCount(5, $refundResults);
        foreach ($refundResults as $result) {
            $this->assertTrue($result['success']);
            $this->assertEquals('REFUNDED', $result['status']);
        }
    }

    /**
     * E2E: Refund with insufficient funds in merchant account
     *
     * Scenario:
     * Refund fails due to insufficient funds
     */
    public function testRefundFailsDueToInsufficientMerchantFunds(): void
    {
        $transactionId = 'TXN_REFUND_FAIL';

        $errorData = [
            'success' => false,
            'message' => 'Insufficient funds in merchant account',
            'errorCode' => 'INSUFFICIENT_FUNDS',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withResponse($errorData, 400);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            $client->refundPayment($transactionId);
            $this->fail('Expected exception for insufficient merchant funds');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Insufficient funds', $e->getMessage());
        }

        // Merchant needs to add funds before processing refunds
    }

    /**
     * E2E: Cannot refund more than original transaction
     *
     * Scenario:
     * Validation prevents refunding more than was charged
     */
    public function testCannotRefundMoreThanOriginalAmount(): void
    {
        $transactionId = 'TXN_REFUND_EXCESS';

        $errorData = [
            'success' => false,
            'message' => 'Refund amount exceeds original transaction amount',
            'errorCode' => 'REFUND_AMOUNT_EXCEEDED',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withResponse($errorData, 400);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            // Original transaction was €50, attempting to refund €75
            $client->refundPayment($transactionId, 75.00);
            $this->fail('Expected exception for excessive refund amount');
        } catch (\Exception $e) {
            $this->assertStringContainsString('exceeds', $e->getMessage());
        }
    }

    /**
     * E2E: Refund expired transaction
     *
     * Scenario:
     * Cannot refund transactions older than refund window
     */
    public function testCannotRefundExpiredTransaction(): void
    {
        $transactionId = 'TXN_TOO_OLD';

        $errorData = [
            'success' => false,
            'message' => 'Transaction is outside the refund window',
            'errorCode' => 'REFUND_WINDOW_EXPIRED',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withResponse($errorData, 400);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            $client->refundPayment($transactionId);
            $this->fail('Expected exception for expired refund window');
        } catch (\Exception $e) {
            $this->assertStringContainsString('refund window', $e->getMessage());
        }

        // Typically refund window is 180 days
    }

    /**
     * E2E: Refund already refunded transaction
     *
     * Scenario:
     * Prevent duplicate refunds
     */
    public function testCannotRefundAlreadyRefundedTransaction(): void
    {
        $transactionId = 'TXN_ALREADY_REFUNDED';

        $errorData = [
            'success' => false,
            'message' => 'Transaction has already been fully refunded',
            'errorCode' => 'ALREADY_REFUNDED',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withResponse($errorData, 400);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            $client->refundPayment($transactionId);
            $this->fail('Expected exception for already refunded transaction');
        } catch (\Exception $e) {
            $this->assertStringContainsString('already been', $e->getMessage());
        }
    }

    /**
     * E2E: Refund declined transaction
     *
     * Scenario:
     * Cannot refund a declined payment
     */
    public function testCannotRefundDeclinedTransaction(): void
    {
        $transactionId = 'TXN_WAS_DECLINED';

        $errorData = [
            'success' => false,
            'message' => 'Cannot refund a declined transaction',
            'errorCode' => 'INVALID_TRANSACTION_STATUS',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withResponse($errorData, 400);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            $client->refundPayment($transactionId);
            $this->fail('Expected exception for refunding declined transaction');
        } catch (\Exception $e) {
            $this->assertStringContainsString('declined', $e->getMessage());
        }

        // Can only refund successful (PROCESSED) transactions
    }

    /**
     * E2E: Service credit vs refund to card
     *
     * Scenario:
     * Business logic for handling refunds as store credit
     */
    public function testRefundWithStoreCreditOption(): void
    {
        $transactionId = 'TXN_CREDIT_OPTION';

        // This would typically be handled in application logic
        // GPG always refunds to original payment method
        // Store credit is tracked separately in merchant system

        $mockClient = MockHttpClient::withResponse(
            ApiResponses::successfulRefund($transactionId)
        );

        $client = $this->createClientWithMockHttp($mockClient);

        // Customer opts for GPG refund (not store credit)
        $refundResponse = $client->refundPayment($transactionId);

        $this->assertTrue($refundResponse->isSuccess());

        // If customer chose store credit instead,
        // merchant would NOT call refundPayment()
        // and would credit customer account in their own system
    }

    /**
     * E2E: Refund tracking and reconciliation
     *
     * Scenario:
     * Track all refunds for accounting
     */
    public function testRefundTrackingForReconciliation(): void
    {
        $transactions = [
            ['id' => 'TXN_001', 'amount' => 50.00, 'refund_amount' => 50.00],
            ['id' => 'TXN_002', 'amount' => 100.00, 'refund_amount' => 30.00],
            ['id' => 'TXN_003', 'amount' => 75.00, 'refund_amount' => 75.00],
        ];

        $responses = [];
        foreach ($transactions as $tx) {
            $refundData = ApiResponses::successfulRefund($tx['id']);
            $refundData['result']['amount'] = $tx['refund_amount'];
            $responses[] = $refundData;
        }

        $mockClient = (new MockHttpClient())
            ->addResponses($responses)
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        $refundLog = [];
        $totalRefunded = 0;

        foreach ($transactions as $tx) {
            $response = $client->refundPayment(
                transactionId: $tx['id'],
                amount: $tx['refund_amount']
            );

            if ($response->isSuccess()) {
                $refundLog[] = [
                    'transaction_id' => $tx['id'],
                    'original_amount' => $tx['amount'],
                    'refund_amount' => $tx['refund_amount'],
                    'refunded_at' => date('Y-m-d H:i:s')
                ];

                $totalRefunded += $tx['refund_amount'];
            }
        }

        $this->assertCount(3, $refundLog);
        $this->assertEquals(155.00, $totalRefunded);

        // Refund log used for accounting reconciliation
    }
}