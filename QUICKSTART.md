# Quick Start Guide - Malta GPG SDK

Get up and running with the Malta Government Payment Gateway SDK in 5 minutes.

## Installation

```bash
composer require born-mt/mita-gpg-sdk
```

## Basic Setup

### 1. Initialize the Client

```php
<?php

require 'vendor/autoload.php';

use BornMT\MitaGpg\Client\GpgClient;

$client = new GpgClient(
    apiKey: 'your-api-key-here',
    testMode: true // Use true for testing
);
```

### 2. Create a Payment

```php
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

// Create payment request
$request = new PaymentRequest(
    amount: 50.00,
    uniqueReference: 'ORDER_' . uniqid(),
    transactionType: TransactionType::SALE,
    customerEmail: 'customer@example.com',
    customerFirstName: 'John',
    customerLastName: 'Doe',
    description: 'Product Purchase',
    redirectUrl: 'https://yoursite.com/payment/success',
    callbackUrl: 'https://yoursite.com/webhook/gpg',
    cancelUrl: 'https://yoursite.com/payment/cancel'
);

// Send payment request
try {
    $response = $client->createPayment($request);

    if ($response->isSuccess()) {
        // Get payment URL and redirect customer
        $paymentUrl = $client->buildPaymentPageUrl($response->getTransactionId());

        echo "Payment created! Transaction ID: " . $response->getTransactionId() . "\n";
        echo "Payment URL: $paymentUrl\n";

        // Redirect user to payment page
        header("Location: $paymentUrl");
        exit;
    } else {
        echo "Payment creation failed: " . $response->getMessage();
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 3. Handle Webhook Callbacks

Create a webhook endpoint to receive payment status updates:

```php
<?php
// webhook.php

require 'vendor/autoload.php';

use BornMT\MitaGpg\Client\GpgClient;

$client = new GpgClient(
    apiKey: 'your-api-key-here',
    testMode: true
);

// Get webhook payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_GPG_SIGNATURE'] ?? null;
$secret = 'your-webhook-secret';

try {
    // Parse and verify webhook
    $webhook = $client->parseWebhook($payload, $signature, $secret);

    // Handle payment status
    if ($webhook->isProcessed()) {
        // Payment successful
        $transactionId = $webhook->getTransactionId();
        $amount = $webhook->getAmount();
        $orderRef = $webhook->getUniqueReference();

        // Update your database
        echo "Payment successful! Transaction: $transactionId\n";

        // TODO: Update order status in your database
        // TODO: Send confirmation email

    } elseif ($webhook->isDeclined()) {
        // Payment declined
        echo "Payment declined\n";

        // TODO: Handle failed payment
    }

    // Always return 200 to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (\BornMT\MitaGpg\Exceptions\InvalidSignatureException $e) {
    // Invalid signature
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
}
```

## Common Use Cases

### Pre-Authorization (Hotel Booking)

```php
// Step 1: Pre-authorize payment
$authRequest = new PaymentRequest(
    amount: 150.00,
    uniqueReference: 'BOOKING_' . uniqid(),
    transactionType: TransactionType::AUTH, // Pre-authorize
    customerEmail: 'guest@hotel.com',
    description: 'Hotel Reservation'
);

$authResponse = $client->createPayment($authRequest);
$transactionId = $authResponse->getTransactionId();

// Redirect user to payment page...
// User completes payment...

// Step 2: Capture payment later (e.g., at check-in)
$captureResponse = $client->capturePayment(
    transactionId: $transactionId,
    amount: 150.00 // Can be partial amount
);

if ($captureResponse->isSuccess()) {
    echo "Payment captured!";
}
```

### Refund Processing

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
    echo "Refund processed!";
}
```

### Get Transaction Details

```php
$transaction = $client->getTransaction('transaction-id');

echo "Status: " . $transaction['result']['status'] . "\n";
echo "Amount: " . $transaction['result']['amount'] . " EUR\n";
echo "Card: " . $transaction['result']['cardNumber'] . "\n";
```

## Configuration Options

### Environment Variables

Create a `.env` file:

```env
GPG_API_KEY=your_api_key_here
GPG_TEST_MODE=true
GPG_WEBHOOK_SECRET=your_webhook_secret
GPG_TIMEOUT=30
```

### Custom Configuration

```php
$client = new GpgClient(
    apiKey: $_ENV['GPG_API_KEY'],
    testMode: $_ENV['GPG_TEST_MODE'] === 'true',
    timeout: 30, // seconds
    options: [
        // Additional Guzzle options
        'verify' => true,
        'connect_timeout' => 10
    ]
);
```

## Error Handling

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
    echo "Authentication failed: " . $e->getMessage();

} catch (ValidationException $e) {
    // Invalid request data
    echo "Validation errors: ";
    print_r($e->getErrors());

} catch (NetworkException $e) {
    // Connection issue
    echo "Network error: " . $e->getMessage();

} catch (ApiException $e) {
    // Other API error
    echo "API error: " . $e->getMessage();
}
```

## Testing

The SDK includes test mode support. Set `testMode: true` when creating the client:

```php
$client = new GpgClient(
    apiKey: 'your-test-api-key',
    testMode: true // Test mode enabled
);
```

**Test Cards** (provided by MITA):
- Use test credit card numbers provided in the GPG documentation
- Test transactions won't charge real money

## Next Steps

1. **Get API Credentials**
   - Test: Contact support@apcopay.com
   - Production: Raise eRFS to MITA

2. **Configure Webhook URL**
   - Set your webhook URL in the GPG portal
   - Use ngrok for local testing

3. **Read Full Documentation**
   - [README.md](README.md) - Complete documentation
   - [EXAMPLES.md](EXAMPLES.md) - Real-world examples
   - [API Docs](https://gpgapi.redoc.ly/) - Official API reference

## Support

- **GPG Support**: support@apcopay.com
- **MITA**: cmd.mita@gov.mt / +356 21234710
- **Package Issues**: https://github.com/born-mt/mita-gpg-sdk/issues

## Key Points to Remember

1. **Always verify webhook signatures** - Prevents spoofing
2. **Use unique references** - Every payment needs a unique reference
3. **Handle webhooks** - Critical for payment confirmation
4. **Test thoroughly** - Use test mode before production
5. **Log everything** - Keep audit trail of transactions
6. **HTTPS in production** - Required for security
7. **Never expose API keys** - Use environment variables

---

That's it! You're ready to start accepting payments through Malta GPG.

For more advanced usage, check out [EXAMPLES.md](EXAMPLES.md).