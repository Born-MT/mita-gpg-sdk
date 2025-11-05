<?php

/**
 * Malta GPG SDK - Simple Demo
 *
 * This is a basic demonstration of how to use the SDK.
 * Replace the API key with your actual test/production key.
 */

require __DIR__ . '/vendor/autoload.php';

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;
use BornMT\MitaGpg\Exceptions\{
    AuthenticationException,
    ValidationException,
    ApiException,
    NetworkException
};

// Configuration
$API_KEY = getenv('GPG_API_KEY') ?: 'your_test_api_key_here';
$TEST_MODE = true;

echo "===========================================\n";
echo "  Malta GPG SDK - Demo Script\n";
echo "===========================================\n\n";

// Initialize the client
echo "1. Initializing GPG Client...\n";
$client = new GpgClient(
    apiKey: $API_KEY,
    testMode: $TEST_MODE
);

echo "   ✓ Client initialized successfully\n";
echo "   Mode: " . ($TEST_MODE ? 'TEST' : 'PRODUCTION') . "\n\n";

// Create a payment request
echo "2. Creating payment request...\n";
$uniqueRef = 'DEMO_' . time() . '_' . uniqid();

$request = new PaymentRequest(
    amount: 25.00,
    uniqueReference: $uniqueRef,
    transactionType: TransactionType::SALE,
    customerEmail: 'demo@example.com',
    customerFirstName: 'John',
    customerLastName: 'Doe',
    description: 'Demo Payment - Test Transaction',
    redirectUrl: 'https://example.com/success',
    callbackUrl: 'https://example.com/webhook',
    cancelUrl: 'https://example.com/cancel',
    isTest: $TEST_MODE
);

// Add custom fields
$request->setUdfField(1, 'Demo Order')
        ->setUdfField(2, 'Customer ID: 123')
        ->addMetadata('demo', 'true');

echo "   ✓ Payment request created\n";
echo "   Amount: €25.00\n";
echo "   Reference: {$uniqueRef}\n\n";

// Send the payment request
echo "3. Sending payment request to GPG API...\n";

try {
    $response = $client->createPayment($request);

    if ($response->isSuccess()) {
        echo "   ✓ Payment created successfully!\n\n";

        echo "   Transaction Details:\n";
        echo "   -------------------\n";
        echo "   Transaction ID: " . $response->getTransactionId() . "\n";
        echo "   Gateway ID: " . $response->getGatewayId() . "\n";
        echo "   Status: " . $response->getStatus()?->value . "\n";
        echo "   Process ID: " . $response->getProcessId() . "\n\n";

        // Build payment URL
        $paymentUrl = $client->buildPaymentPageUrl($response->getTransactionId());

        echo "   Payment URL:\n";
        echo "   {$paymentUrl}\n\n";

        echo "   Next Steps:\n";
        echo "   ----------\n";
        echo "   1. Open the payment URL in your browser\n";
        echo "   2. Enter test card details on the secure payment page\n";
        echo "   3. Complete 3D Secure authentication\n";
        echo "   4. You will be redirected back to the redirect URL\n";
        echo "   5. A webhook will be sent to your callback URL\n\n";

    } else {
        echo "   ✗ Payment creation failed\n";
        echo "   Error: " . $response->getMessage() . "\n\n";
    }

} catch (AuthenticationException $e) {
    echo "   ✗ Authentication Error!\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   \n";
    echo "   Please check your API key and ensure it's valid.\n";
    echo "   For test keys, contact: support@apcopay.com\n\n";

} catch (ValidationException $e) {
    echo "   ✗ Validation Error!\n";
    echo "   Message: " . $e->getMessage() . "\n";
    if ($e->getErrors()) {
        echo "   Errors:\n";
        foreach ($e->getErrors() as $field => $error) {
            echo "     - {$field}: {$error}\n";
        }
    }
    echo "\n";

} catch (NetworkException $e) {
    echo "   ✗ Network Error!\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   \n";
    echo "   Please check your internet connection and try again.\n\n";

} catch (ApiException $e) {
    echo "   ✗ API Error!\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   Status Code: " . $e->getStatusCode() . "\n\n";

} catch (Exception $e) {
    echo "   ✗ Unexpected Error!\n";
    echo "   Message: " . $e->getMessage() . "\n\n";
}

// Demonstrate webhook signature verification
echo "4. Webhook Signature Verification Demo...\n";

$webhookPayload = json_encode([
    'transactionId' => 'TXN_DEMO_123',
    'gatewayId' => 'GW_456',
    'status' => 'PROCESSED',
    'transactionType' => 'SALE',
    'amount' => 25.00,
    'currency' => 'EUR',
    'authCode' => 'AUTH789',
    'cardNumber' => '4111****1111',
    'cardScheme' => 'VISA',
    'uniqueReference' => $uniqueRef
]);

$webhookSecret = 'demo_webhook_secret';
$validSignature = hash_hmac('sha256', $webhookPayload, $webhookSecret);

echo "   Testing signature verification...\n";
$isValid = $client->verifyWebhookSignature($webhookPayload, $validSignature, $webhookSecret);

if ($isValid) {
    echo "   ✓ Webhook signature is VALID\n\n";

    // Parse the webhook
    $webhook = $client->parseWebhook($webhookPayload, $validSignature, $webhookSecret);

    echo "   Parsed Webhook Data:\n";
    echo "   -------------------\n";
    echo "   Transaction ID: " . $webhook->getTransactionId() . "\n";
    echo "   Status: " . $webhook->getStatus()->value . "\n";
    echo "   Amount: €" . $webhook->getAmount() . "\n";
    echo "   Card: " . $webhook->getCardNumber() . "\n";
    echo "   Is Processed: " . ($webhook->isProcessed() ? 'Yes' : 'No') . "\n\n";
} else {
    echo "   ✗ Webhook signature is INVALID\n\n";
}

echo "===========================================\n";
echo "  Demo completed!\n";
echo "===========================================\n\n";

echo "For more examples, see:\n";
echo "  - README.md - Complete documentation\n";
echo "  - QUICKSTART.md - 5-minute setup guide\n";
echo "  - EXAMPLES.md - Real-world usage examples\n\n";

echo "Support:\n";
echo "  - GPG API Docs: https://gpgapi.redoc.ly/\n";
echo "  - GPG Support: support@apcopay.com\n";
echo "  - MITA: cmd.mita@gov.mt\n\n";