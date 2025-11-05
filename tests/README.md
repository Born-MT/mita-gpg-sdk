# Malta GPG SDK - Test Suite Documentation

Complete E2E test suite for the Malta Government Payment Gateway SDK.

## Quick Start

```bash
# Run all tests (no warnings!)
composer test

# Run with detailed output
vendor/bin/phpunit --testdox

# Run specific test suite
vendor/bin/phpunit tests/E2E/PaymentFlowTest.php
```

**Result:** ✅ 63 tests, 232 assertions, ~0.03 seconds, 0 API calls

## Overview

This test suite provides comprehensive coverage of all SDK functionality without making actual API calls. All tests use mocked HTTP responses to simulate real-world scenarios.

## Test Structure

```
tests/
├── GpgClientTest.php           # Unit tests for core functionality
├── E2E/                        # End-to-end integration tests
│   ├── PaymentFlowTest.php     # Complete payment workflows
│   ├── PreAuthCaptureFlowTest.php  # Pre-auth and capture scenarios
│   ├── RefundFlowTest.php      # Refund processing
│   ├── WebhookProcessingTest.php   # Webhook handling
│   └── ErrorScenariosTest.php  # Error conditions
├── Fixtures/                   # Test data
│   └── ApiResponses.php        # Mock API response data
└── Helpers/                    # Test utilities
    └── MockHttpClient.php      # HTTP client mocking
```

## Test Categories

### Unit Tests (GpgClientTest.php)
**13 tests, 59 assertions**

Tests core SDK functionality:
- Client instantiation and configuration
- Payment URL building
- Webhook signature verification
- DTO conversions and validation
- Enum values
- Fluent interface methods

**Run:** `vendor/bin/phpunit tests/GpgClientTest.php`

### E2E Tests

#### 1. Payment Flow Tests
**Tests:** 6 payment scenarios
**File:** `tests/E2E/PaymentFlowTest.php`

Scenarios covered:
- ✅ Complete successful payment flow (create → redirect → webhook → confirm)
- ✅ Customer abandonment (PENDING status)
- ✅ Card decline handling
- ✅ Multiple payments in sequence
- ✅ Payment with extensive metadata/UDF fields
- ✅ Transaction history queries

**Run:** `vendor/bin/phpunit tests/E2E/PaymentFlowTest.php`

#### 2. Pre-Authorization & Capture Tests
**Tests:** 7 pre-auth scenarios
**File:** `tests/E2E/PreAuthCaptureFlowTest.php`

Scenarios covered:
- ✅ Hotel reservation (pre-auth → capture)
- ✅ Additional charges at checkout
- ✅ Partial capture with remaining funds released
- ✅ Cancelled reservation (void pre-auth)
- ✅ Car rental with deposit hold
- ✅ Duplicate capture prevention
- ✅ Expired pre-authorization handling

**Run:** `vendor/bin/phpunit tests/E2E/PreAuthCaptureFlowTest.php`

#### 3. Refund Flow Tests
**Tests:** 10 refund scenarios
**File:** `tests/E2E/RefundFlowTest.php`

Scenarios covered:
- ✅ Full refund for returns
- ✅ Partial refund for damaged items
- ✅ Multiple partial refunds
- ✅ Bulk refunds (event cancellation)
- ✅ Insufficient merchant funds error
- ✅ Refund amount validation
- ✅ Expired refund window
- ✅ Duplicate refund prevention
- ✅ Declining refunds for declined transactions
- ✅ Refund tracking and reconciliation

**Run:** `vendor/bin/phpunit tests/E2E/RefundFlowTest.php`

#### 4. Webhook Processing Tests
**Tests:** 12 webhook scenarios
**File:** `tests/E2E/WebhookProcessingTest.php`

Scenarios covered:
- ✅ Complete webhook processing workflow
- ✅ Declined payment webhooks
- ✅ Invalid signature rejection (security)
- ✅ Timing-safe signature comparison
- ✅ Missing signature handling
- ✅ Duplicate webhook handling (idempotency)
- ✅ Out-of-order webhook handling
- ✅ Malformed JSON handling
- ✅ Retry mechanism simulation
- ✅ Pre-authorized payment webhooks
- ✅ Comprehensive data extraction
- ✅ Case-insensitive field handling

**Run:** `vendor/bin/phpunit tests/E2E/WebhookProcessingTest.php`

#### 5. Error Scenarios Tests
**Tests:** 14 error conditions
**File:** `tests/E2E/ErrorScenariosTest.php`

Scenarios covered:
- ✅ Authentication failure (401)
- ✅ Validation errors (422)
- ✅ Network timeout
- ✅ Server error (500)
- ✅ Transaction not found (404)
- ✅ Rate limiting (429)
- ✅ Duplicate transaction (409)
- ✅ Invalid amount validation
- ✅ Currency mismatch
- ✅ Merchant account suspended (403)
- ✅ Malformed JSON response
- ✅ Service outage with retry
- ✅ Invalid transaction type for operation
- ✅ Service maintenance window

**Run:** `vendor/bin/phpunit tests/E2E/ErrorScenariosTest.php`

## Running Tests

### Run All Tests
```bash
cd /path/to/mita-gpg-sdk
composer test
```

### Run Specific Test Suite
```bash
vendor/bin/phpunit tests/GpgClientTest.php
vendor/bin/phpunit tests/E2E/PaymentFlowTest.php
vendor/bin/phpunit tests/E2E/PreAuthCaptureFlowTest.php
vendor/bin/phpunit tests/E2E/RefundFlowTest.php
vendor/bin/phpunit tests/E2E/WebhookProcessingTest.php
vendor/bin/phpunit tests/E2E/ErrorScenariosTest.php
```

### Run Specific Test
```bash
vendor/bin/phpunit --filter testCompleteSuccessfulPaymentFlow
vendor/bin/phpunit --filter testHotelReservationWithPreAuthAndCapture
vendor/bin/phpunit --filter testFullRefundForCustomerReturn
```

### Run with Coverage (requires xdebug)
```bash
composer test-coverage
```

### Verbose Output
```bash
vendor/bin/phpunit --testdox
```

## Test Statistics

**Total Test Coverage:**
- **62+ test methods**
- **200+ assertions**
- **0 actual API calls** (all mocked)
- **100% offline testing**

**Coverage by Feature:**
- ✅ Payment creation (SALE)
- ✅ Pre-authorization (AUTH)
- ✅ Payment capture (CAPT)
- ✅ Refunds (REFUND) - full & partial
- ✅ Void transactions (VOID)
- ✅ Transaction queries
- ✅ Transaction lists
- ✅ Webhook verification
- ✅ Webhook parsing
- ✅ Error handling
- ✅ Exception types
- ✅ DTOs and Enums
- ✅ Security (signature verification)

## Mock HTTP Client

The `MockHttpClient` helper provides easy HTTP response mocking:

```php
use BornMT\MitaGpg\Tests\Helpers\MockHttpClient;
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;

// Single response
$mockClient = MockHttpClient::withResponse(
    ApiResponses::successfulPaymentCreation('TXN_123')
);

// Multiple responses
$mockClient = (new MockHttpClient())
    ->addJsonResponse(ApiResponses::successfulPaymentCreation('TXN_1'))
    ->addJsonResponse(ApiResponses::successfulCapture('TXN_1'))
    ->build();

// Error response
$mockClient = MockHttpClient::withError(401, ApiResponses::authenticationError());

// Network error
$mockClient = MockHttpClient::withNetworkError('Connection timeout');
```

## Test Fixtures

Pre-defined API responses in `ApiResponses.php`:

**Success Responses:**
- `successfulPaymentCreation($txId)`
- `successfulCapture($txId)`
- `successfulRefund($txId)`
- `transactionDetails($txId, $status)`
- `transactionList()`

**Webhook Payloads:**
- `webhookProcessedPayment($orderRef)`
- `webhookDeclinedPayment($orderRef)`
- `webhookAuthorizedPayment($bookingRef)`

**Error Responses:**
- `authenticationError()` - 401
- `validationError()` - 422
- `serverError()` - 500
- `transactionNotFound()` - 404

## Adding New Tests

### Step 1: Create Test Class
```php
<?php

namespace BornMT\MitaGpg\Tests\E2E;

use PHPUnit\Framework\TestCase;
use BornMT\MitaGpg\Tests\Helpers\MockHttpClient;
use BornMT\MitaGpg\Tests\Fixtures\ApiResponses;

class YourNewTest extends TestCase
{
    public function testYourScenario(): void
    {
        // Setup mock
        $mockClient = MockHttpClient::withResponse(
            ApiResponses::successfulPaymentCreation('TXN_TEST')
        );

        // Create client with mock
        $client = $this->createClientWithMockHttp($mockClient);

        // Test logic
        // ...

        // Assertions
        $this->assertTrue($response->isSuccess());
    }

    private function createClientWithMockHttp($mockClient): GpgClient
    {
        $client = new GpgClient(apiKey: 'test', testMode: true);

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $mockClient);

        return $client;
    }
}
```

### Step 2: Add Test Fixture (if needed)
Add new mock response to `ApiResponses.php`:

```php
public static function yourNewResponse(): array
{
    return [
        'success' => true,
        'result' => [
            // ... your response data
        ]
    ];
}
```

### Step 3: Run New Test
```bash
vendor/bin/phpunit tests/E2E/YourNewTest.php
```

## Continuous Integration

### GitHub Actions
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - run: composer install
      - run: composer test
```

### Local Pre-commit Hook
```bash
#!/bin/sh
composer test
if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi
```

## Testing Best Practices

### ✅ DO
- Mock all HTTP requests
- Test both success and failure scenarios
- Verify exception types and messages
- Test edge cases (empty values, nulls, etc.)
- Use descriptive test method names
- Add assertions to verify data transformations
- Test idempotency where applicable

### ❌ DON'T
- Make actual API calls in tests
- Hard-code API keys in tests
- Skip error scenario tests
- Test only happy paths
- Commit sensitive test data

## Troubleshooting

### Tests Failing
1. **Check PHP version**: Requires PHP 8.1+
   ```bash
   php -v
   ```

2. **Update dependencies**:
   ```bash
   composer update
   ```

3. **Clear cache**:
   ```bash
   rm -rf .phpunit.cache
   ```

### Slow Tests
- All tests should be fast (< 1 second each)
- If tests are slow, check for actual API calls
- Use mocks for all external dependencies

### Debugging
Enable verbose output:
```bash
vendor/bin/phpunit --verbose --debug
```

## Resources

- **PHPUnit Docs**: https://phpunit.de/documentation.html
- **Guzzle Mocking**: https://docs.guzzlephp.org/en/stable/testing.html
- **Malta GPG API**: https://gpgapi.redoc.ly/

## Contributing

When adding new features:
1. Write tests first (TDD)
2. Ensure all tests pass
3. Add test documentation
4. Update this README if needed

---

**All tests are fully mocked and safe to run without API credentials.**