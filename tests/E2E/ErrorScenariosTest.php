<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\E2E;

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;
use BornMT\MitaGpg\Exceptions\{
    AuthenticationException,
    ValidationException,
    ApiException,
    NetworkException
};
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;
use BornMT\MitaGpg\Tests\Helpers\MockHttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * E2E Test: Error Scenarios and Edge Cases
 *
 * Tests various error conditions and how the SDK handles them
 */
class ErrorScenariosTest extends TestCase
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
     * E2E: Authentication failure with invalid API key
     */
    public function testAuthenticationFailureWithInvalidApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed');

        $mockClient = MockHttpClient::withError(
            401,
            ApiResponses::authenticationError()
        );

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        $client->createPayment($request);
    }

    /**
     * E2E: Validation errors for invalid request data
     */
    public function testValidationErrorsForInvalidData(): void
    {
        $this->expectException(ValidationException::class);

        $mockClient = MockHttpClient::withError(
            422,
            ApiResponses::validationError()
        );

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: '' // Invalid: empty reference
        );

        try {
            $client->createPayment($request);
        } catch (ValidationException $e) {
            // Verify error details
            $this->assertNotEmpty($e->getErrors());
            $this->assertArrayHasKey('Amount', $e->getErrors());
            $this->assertArrayHasKey('UniqueReference', $e->getErrors());
            $this->assertArrayHasKey('CustomerEmail', $e->getErrors());

            throw $e;
        }
    }

    /**
     * E2E: Network connection timeout
     */
    public function testNetworkConnectionTimeout(): void
    {
        $this->expectException(NetworkException::class);
        $this->expectExceptionMessageMatches('/Connection timeout|Network error|Request failed/');

        $mockClient = MockHttpClient::withNetworkError('Connection timeout');

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        $client->createPayment($request);
    }

    /**
     * E2E: Server error (500)
     */
    public function testServerInternalError(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server error');

        $mockClient = MockHttpClient::withError(
            500,
            ApiResponses::serverError()
        );

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        try {
            $client->createPayment($request);
        } catch (ApiException $e) {
            $this->assertEquals(500, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * E2E: Transaction not found (404)
     */
    public function testTransactionNotFound(): void
    {
        $this->expectException(ApiException::class);

        $mockClient = MockHttpClient::withError(
            404,
            ApiResponses::transactionNotFound()
        );

        $client = $this->createClientWithMockHttp($mockClient);

        try {
            $client->getTransaction('TXN_DOES_NOT_EXIST');
        } catch (ApiException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertStringContainsString('not found', $e->getMessage());
            throw $e;
        }
    }

    /**
     * E2E: Rate limiting (429)
     */
    public function testRateLimitingError(): void
    {
        $this->expectException(ApiException::class);

        $rateLimitError = [
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'errorCode' => 'RATE_LIMIT_EXCEEDED',
            'retryAfter' => 60,
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(429, $rateLimitError);
        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        try {
            $client->createPayment($request);
        } catch (ApiException $e) {
            $this->assertEquals(429, $e->getStatusCode());
            $this->assertStringContainsString('Too many requests', $e->getMessage());
            throw $e;
        }
    }

    /**
     * E2E: Duplicate transaction - same unique reference
     */
    public function testDuplicateTransactionError(): void
    {
        $this->expectException(ApiException::class);

        $duplicateError = [
            'success' => false,
            'message' => 'A transaction with this unique reference already exists',
            'errorCode' => 'DUPLICATE_REFERENCE',
            'existingTransactionId' => 'TXN_ORIGINAL_12345',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(409, $duplicateError);
        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_DUPLICATE' // Already used
        );

        try {
            $client->createPayment($request);
        } catch (ApiException $e) {
            $this->assertEquals(409, $e->getStatusCode());
            $this->assertStringContainsString('already exists', $e->getMessage());
            throw $e;
        }
    }

    /**
     * E2E: Invalid amount (negative or zero)
     */
    public function testInvalidAmountError(): void
    {
        $this->expectException(ValidationException::class);

        $invalidAmountError = [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => [
                'Amount' => 'Amount must be greater than 0'
            ],
            'errorCode' => 'VALIDATION_ERROR',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(422, $invalidAmountError);
        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: -10.00, // Invalid: negative amount
            uniqueReference: 'ORDER_' . time()
        );

        $client->createPayment($request);
    }

    /**
     * E2E: Currency mismatch error
     */
    public function testCurrencyMismatchError(): void
    {
        $this->expectException(ValidationException::class);

        $currencyError = [
            'success' => false,
            'message' => 'Invalid currency. Only EUR is supported.',
            'errors' => [
                'Currency' => 'Only EUR currency is accepted'
            ],
            'errorCode' => 'INVALID_CURRENCY',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(422, $currencyError);
        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        try {
            $client->createPayment($request);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('Currency', $errors);
            throw $e;
        }
    }

    /**
     * E2E: Merchant account suspended
     */
    public function testMerchantAccountSuspendedError(): void
    {
        $this->expectException(ApiException::class);

        $suspendedError = [
            'success' => false,
            'message' => 'Merchant account is suspended. Please contact support.',
            'errorCode' => 'ACCOUNT_SUSPENDED',
            'supportEmail' => 'support@apcopay.com',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(403, $suspendedError);
        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        try {
            $client->createPayment($request);
        } catch (ApiException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('suspended', $e->getMessage());
            throw $e;
        }
    }

    /**
     * E2E: Malformed JSON response
     */
    public function testMalformedJsonResponse(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $mockClient = (new MockHttpClient())
            ->addJsonResponse(['invalid' => 'response'], 200)
            ->build();

        // Manually set response to invalid JSON
        $reflection = new ReflectionClass(MockHttpClient::class);

        $mockClient = new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(
                new \GuzzleHttp\Handler\MockHandler([
                    new \GuzzleHttp\Psr7\Response(200, [], 'Invalid JSON {{{')
                ])
            )
        ]);

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        $client->createPayment($request);
    }

    /**
     * E2E: Partial service outage with retry
     */
    public function testPartialServiceOutageWithSuccessfulRetry(): void
    {
        // First request fails, second succeeds
        $mockClient = (new MockHttpClient())
            ->addErrorResponse(503, ApiResponses::serverError())
            ->addJsonResponse(ApiResponses::successfulPaymentCreation('TXN_RETRY_SUCCESS'))
            ->build();

        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_RETRY_' . time()
        );

        // First attempt fails
        try {
            $client->createPayment($request);
            $this->fail('Expected first request to fail');
        } catch (ApiException $e) {
            $this->assertEquals(503, $e->getStatusCode());
        }

        // Retry succeeds
        $response = $client->createPayment($request);
        $this->assertTrue($response->isSuccess());
    }

    /**
     * E2E: Invalid transaction type for operation
     */
    public function testInvalidTransactionTypeForOperation(): void
    {
        $invalidTypeError = [
            'success' => false,
            'message' => 'Cannot capture a SALE transaction. Only AUTH transactions can be captured.',
            'errorCode' => 'INVALID_OPERATION',
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(400, $invalidTypeError);
        $client = $this->createClientWithMockHttp($mockClient);

        try {
            // Attempting to capture a SALE transaction (which is already captured)
            $client->capturePayment('TXN_SALE_NOT_AUTH');
            $this->fail('Expected exception to be thrown');
        } catch (ValidationException|ApiException $e) {
            $this->assertStringContainsString('Cannot capture', $e->getMessage());
        }
    }

    /**
     * E2E: Service maintenance window
     */
    public function testServiceMaintenanceWindow(): void
    {
        $this->expectException(ApiException::class);

        $maintenanceError = [
            'success' => false,
            'message' => 'Service temporarily unavailable due to scheduled maintenance',
            'errorCode' => 'SERVICE_MAINTENANCE',
            'estimatedRestoration' => date('Y-m-d\TH:i:s\Z', strtotime('+2 hours')),
            'dateTime' => date('Y-m-d\TH:i:s\Z')
        ];

        $mockClient = MockHttpClient::withError(503, $maintenanceError);
        $client = $this->createClientWithMockHttp($mockClient);

        $request = new PaymentRequest(
            amount: 50.00,
            uniqueReference: 'ORDER_' . time()
        );

        try {
            $client->createPayment($request);
        } catch (ApiException $e) {
            $this->assertEquals(503, $e->getStatusCode());
            $this->assertStringContainsString('maintenance', $e->getMessage());
            throw $e;
        }
    }
}