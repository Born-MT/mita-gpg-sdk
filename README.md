# Malta GPG SDK

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A comprehensive PHP SDK for integrating with Malta's Government Payment Gateway (GPG) API. This package provides a clean, type-safe interface for processing payments through Malta's official payment infrastructure.

## Features

- **Complete API Coverage**: All GPG endpoints (Hosted Payment Page, Transactions, Reporting)
- **Type Safety**: Full PHP 8.1+ enums and typed properties
- **Payment Operations**: SALE, AUTH, CAPTURE, REFUND, VOID
- **Webhook Support**: Built-in signature verification and payload parsing
- **Exception Handling**: Comprehensive error handling with specific exceptions
- **Test Mode**: Built-in sandbox support
- **DTOs**: Clean data transfer objects for requests and responses
- **PSR Compliant**: Follows PHP standards and best practices
- **Framework Agnostic**: Use with Laravel, Symfony, or any PHP project

## Official Documentation

- **API Documentation**: https://gpgapi.redoc.ly/
- **MITA GPG Info**: https://mita.gov.mt/portfolio/information-systems/government-payment-gateway/

## Installation

Install via Composer:

```bash
composer require born-mt/mita-gpg-sdk
```

## Requirements

- PHP 8.1 or higher
- ext-json
- guzzlehttp/guzzle ^7.5

## Quick Start

### Initialize the Client

```php
use BornMT\MitaGpg\Client\GpgClient;

$client = new GpgClient(
    apiKey: 'your-api-key-here',
    testMode: true, // Set to false for production
    timeout: 30 // Optional timeout in seconds
);
```

### Create a Simple Payment

```php
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

// Create payment request
$request = new PaymentRequest(
    amount: 50.00,
    uniqueReference: uniqid('order_'),
    transactionType: TransactionType::SALE,
    customerEmail: 'customer@example.com',
    customerFirstName: 'John',
    customerLastName: 'Doe',
    description: 'Order #12345',
    redirectUrl: 'https://yoursite.com/payment/success',
    callbackUrl: 'https://yoursite.com/webhook/gpg',
    cancelUrl: 'https://yoursite.com/payment/cancel'
);

// Create the payment
$response = $client->createPayment($request);

if ($response->isSuccess()) {
    // Redirect user to payment page
    $paymentUrl = $client->buildPaymentPageUrl($response->getTransactionId());
    header("Location: $paymentUrl");
    exit;
}
```

### Pre-Authorize and Capture

```php
// Step 1: Pre-authorize payment (hold funds)
$authRequest = new PaymentRequest(
    amount: 150.00,
    uniqueReference: 'booking_' . uniqid(),
    transactionType: TransactionType::AUTH,
    customerEmail: 'guest@hotel.com',
    description: 'Hotel Reservation'
);

$authResponse = $client->createPayment($authRequest);
$transactionId = $authResponse->getTransactionId();

// Redirect to payment page...
// User completes 3D Secure authentication...

// Step 2: Later, capture the payment
$captureResponse = $client->capturePayment(
    transactionId: $transactionId,
    amount: 150.00 // Can capture partial amount
);

if ($captureResponse->isSuccess()) {
    echo "Payment captured successfully!";
}
```

### Handle Webhooks

```php
// In your webhook endpoint controller
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_GPG_SIGNATURE'] ?? null;
$secret = 'your-webhook-secret';

try {
    // Parse and verify webhook
    $webhook = $client->parseWebhook($payload, $signature, $secret);

    // Handle the transaction
    if ($webhook->isProcessed()) {
        // Payment successful
        $transactionId = $webhook->getTransactionId();
        $amount = $webhook->getAmount();
        $orderRef = $webhook->getUniqueReference();

        // Update your database
        updateOrder($orderRef, 'paid', $transactionId);

        // Send confirmation email
        sendConfirmationEmail($webhook->getCustomerEmail());
    } elseif ($webhook->isDeclined()) {
        // Payment declined
        handleDeclinedPayment($webhook);
    }

    // Always return 200 to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (\BornMT\MitaGpg\Exceptions\InvalidSignatureException $e) {
    // Invalid signature - possible security issue
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
}
```

### Refund a Payment

```php
// Full refund
$refundResponse = $client->refundPayment(
    transactionId: 'transaction-id-here'
);

// Partial refund
$partialRefund = $client->refundPayment(
    transactionId: 'transaction-id-here',
    amount: 25.00
);

if ($refundResponse->isSuccess()) {
    echo "Refund processed successfully!";
}
```

### Get Transaction Details

```php
$transaction = $client->getTransaction('transaction-id-here');

echo "Status: " . $transaction['result']['status'];
echo "Amount: " . $transaction['result']['amount'];
echo "Card: " . $transaction['result']['cardNumber'];
```

### Get Transaction History

```php
$transactions = $client->getTransactions([
    'startDate' => '2025-01-01',
    'endDate' => '2025-01-31',
    'status' => 'PROCESSED',
    'pageSize' => 50,
    'pageNumber' => 1
]);

foreach ($transactions['result']['transactions'] as $tx) {
    echo "{$tx['transactionId']}: {$tx['amount']} EUR - {$tx['status']}\n";
}
```

## API Reference

### GpgClient

The main client class for interacting with Malta GPG API.

#### Constructor

```php
public function __construct(
    string $apiKey,
    bool $testMode = false,
    int $timeout = 30,
    array $options = []
)
```

#### Methods

##### createPayment(PaymentRequest $request): PaymentResponse
Create a new Hosted Payment Page transaction.

##### capturePayment(string $transactionId, ?float $amount = null): PaymentResponse
Capture a pre-authorized payment (full or partial).

##### refundPayment(string $transactionId, ?float $amount = null): PaymentResponse
Refund a processed payment (full or partial).

##### voidPayment(string $transactionId): PaymentResponse
Cancel/void a pending or authorized transaction.

##### getTransaction(string $transactionId): array
Get details of a specific transaction.

##### getTransactions(array $filters = []): array
Get list of transactions with optional filters.

##### buildPaymentPageUrl(string $transactionId): string
Build the Hosted Payment Page URL for a transaction.

##### verifyWebhookSignature(string $payload, string $signature, string $secret): bool
Verify webhook signature using HMAC SHA256.

##### parseWebhook(string $payload, ?string $signature, ?string $secret): WebhookPayload
Parse and optionally verify webhook payload.

### DTOs

#### PaymentRequest
```php
new PaymentRequest(
    float $amount,
    string $uniqueReference,
    TransactionType $transactionType = TransactionType::SALE,
    ?string $customerEmail = null,
    ?string $customerFirstName = null,
    ?string $customerLastName = null,
    ?string $customerPhone = null,
    ?string $description = null,
    ?string $redirectUrl = null,
    ?string $callbackUrl = null,
    ?string $cancelUrl = null,
    bool $isTest = false,
    array $metadata = [],
    array $udfFields = []
)
```

**Fluent Methods:**
- `setAmount(float $amount)`
- `setCustomerEmail(string $email)`
- `setCustomerName(string $firstName, string $lastName)`
- `setDescription(string $description)`
- `setRedirectUrl(string $url)`
- `setCallbackUrl(string $url)`
- `addMetadata(string $key, mixed $value)`
- `setUdfField(int $fieldNumber, string $value)` (1-5)

#### PaymentResponse
```php
// Methods
isSuccess(): bool
getTransactionId(): ?string
getGatewayId(): ?string
getStatus(): ?TransactionStatus
getPaymentUrl(): ?string
getMessage(): ?string
getRawResponse(): array
```

#### WebhookPayload
```php
// Methods
isProcessed(): bool
isDeclined(): bool
isPending(): bool
getTransactionId(): string
getStatus(): TransactionStatus
getAmount(): float
getAuthCode(): ?string
getCardNumber(): ?string (masked)
getUniqueReference(): ?string
getUdfField(int $fieldNumber): ?string
getRawPayload(): array
```

### Enums

#### TransactionType
- `SALE` - Immediate payment
- `AUTH` - Pre-authorization
- `CAPTURE` - Capture authorized payment
- `REFUND` - Refund payment
- `VOID` - Cancel transaction

#### TransactionStatus
- `PENDING` - Payment initiated
- `PROCESSED` - Payment successful
- `DECLINED` - Payment declined
- `AUTHORIZED` - Pre-authorized
- `REFUNDED` - Payment refunded
- `CANCELLED` - Transaction cancelled
- `FAILED` - Technical failure

### Exceptions

All exceptions extend `GpgException`:

- **AuthenticationException** (401) - Invalid API key
- **ValidationException** (400/422) - Request validation failed
- **ApiException** (4xx/5xx) - General API errors
- **NetworkException** - Connection/network errors
- **InvalidSignatureException** (403) - Webhook signature verification failed

## Advanced Usage

### Custom UDF Fields

```php
$request = new PaymentRequest(
    amount: 100.00,
    uniqueReference: 'order_123'
);

// Add custom user-defined fields (up to 5)
$request->setUdfField(1, 'Customer ID: 456')
        ->setUdfField(2, 'Product SKU: ABC123')
        ->setUdfField(3, 'Campaign: SUMMER2025');

$response = $client->createPayment($request);
```

### Custom Metadata

```php
$request = new PaymentRequest(
    amount: 50.00,
    uniqueReference: 'booking_789'
);

$request->addMetadata('hotel_id', '123')
        ->addMetadata('room_type', 'deluxe')
        ->addMetadata('check_in', '2025-06-01');
```

### Error Handling

```php
use BornMT\MitaGpg\Exceptions\{
    AuthenticationException,
    ValidationException,
    ApiException,
    NetworkException
};

try {
    $response = $client->createPayment($request);
} catch (AuthenticationException $e) {
    // Invalid API key
    log_error("Auth failed: " . $e->getMessage());
    echo "Configuration error. Please contact support.";
} catch (ValidationException $e) {
    // Invalid request data
    $errors = $e->getErrors();
    foreach ($errors as $field => $message) {
        echo "$field: $message\n";
    }
} catch (NetworkException $e) {
    // Connection issue
    echo "Service temporarily unavailable. Please try again.";
} catch (ApiException $e) {
    // Other API error
    log_error("API Error: " . $e->getMessage());
    echo "Payment processing error. Please try again.";
}
```

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

## Getting API Credentials

### For Testing/Development
Contact GPG Support to request test API keys:
- **Email**: support@apcopay.com
- **Phone**: +356 21234710
- Mention you need sandbox/test credentials

### For Production
1. Open merchant accounts with BOV and/or HSBC Malta
2. Ensure accounts are 3D Secure enabled
3. Raise an eRFS (electronic Request for Service) to MITA
4. Provide your bank account details and business information
5. Wait for approval (processing time varies)
6. Receive production API credentials

## Security Best Practices

1. **Never commit API keys** - Use environment variables
2. **Always verify webhook signatures** - Prevent spoofing
3. **Use HTTPS** - Required for production
4. **Validate amounts** - Check amounts match your records
5. **Log everything** - Keep audit trail of transactions
6. **Handle errors gracefully** - Don't expose internal details to users
7. **Test thoroughly** - Use test mode before production
8. **Monitor webhooks** - Alert on missed/failed webhooks

## Payment Flow

1. **Customer initiates payment** on your website
2. **Create payment** via API (POST /api/HostedPaymentPage)
3. **Redirect customer** to GPG Hosted Payment Page
4. **Customer enters card details** on secure GPG page
5. **3D Secure authentication** by customer's bank
6. **Payment processed** by acquiring bank
7. **Customer redirected** back to your site
8. **Webhook sent** with final transaction status
9. **Update your database** based on webhook

## Support

- **GPG Support**: support@apcopay.com
- **MITA**: cmd.mita@gov.mt / +356 21234710
- **Documentation**: https://gpgapi.redoc.ly/
- **Issues**: https://github.com/born-mt/mita-gpg-sdk/issues

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- **Born MT** - https://born.mt
- **Malta Information Technology Agency (MITA)** - https://mita.gov.mt
- **APCOPay** - Payment gateway provider

## Changelog

### 1.0.0 (2025-01-05)
- Initial release
- Full API coverage
- Webhook support
- Comprehensive documentation

---

**Made with ❤️ in Malta**
