# Malta GPG SDK - Usage Examples

This document provides real-world usage examples for common payment scenarios using the Malta GPG SDK.

## Table of Contents

1. [E-commerce Checkout](#1-e-commerce-checkout)
2. [Hotel Reservation with Pre-Authorization](#2-hotel-reservation-with-pre-authorization)
3. [Subscription Payment](#3-subscription-payment)
4. [Marketplace Split Payment](#4-marketplace-split-payment)
5. [Government Service Payment](#5-government-service-payment)
6. [Refund Processing](#6-refund-processing)
7. [Webhook Handler](#7-webhook-handler)
8. [Transaction Reconciliation](#8-transaction-reconciliation)
9. [Error Handling Patterns](#9-error-handling-patterns)
10. [Testing Strategies](#10-testing-strategies)

---

## 1. E-commerce Checkout

Complete checkout flow for an online store.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

class CheckoutController
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: $_ENV['GPG_TEST_MODE'] === 'true'
        );
    }

    public function processCheckout(array $cart, array $customer)
    {
        // Calculate total
        $total = array_sum(array_column($cart, 'price'));

        // Generate unique order reference
        $orderRef = 'ORDER_' . time() . '_' . uniqid();

        // Store order in database
        $orderId = $this->createOrder($orderRef, $cart, $customer, $total);

        // Create payment request
        $request = new PaymentRequest(
            amount: $total,
            uniqueReference: $orderRef,
            transactionType: TransactionType::SALE,
            customerEmail: $customer['email'],
            customerFirstName: $customer['first_name'],
            customerLastName: $customer['last_name'],
            customerPhone: $customer['phone'],
            description: "Order #{$orderId}",
            redirectUrl: "https://yourstore.com/checkout/success?order={$orderId}",
            callbackUrl: "https://yourstore.com/webhooks/gpg",
            cancelUrl: "https://yourstore.com/checkout/cancel?order={$orderId}"
        );

        // Add order details to UDF fields
        $request->setUdfField(1, "Order ID: {$orderId}")
                ->setUdfField(2, "Items: " . count($cart))
                ->setUdfField(3, "Customer ID: {$customer['id']}");

        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                // Save transaction ID
                $this->updateOrderTransactionId($orderId, $response->getTransactionId());

                // Redirect to payment page
                $paymentUrl = $this->gpgClient->buildPaymentPageUrl(
                    $response->getTransactionId()
                );

                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'transaction_id' => $response->getTransactionId()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->getMessage()
                ];
            }
        } catch (\Exception $e) {
            // Log error
            error_log("Payment creation failed: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Unable to process payment. Please try again.'
            ];
        }
    }

    private function createOrder(string $ref, array $cart, array $customer, float $total): int
    {
        // Your database logic
        return 12345;
    }

    private function updateOrderTransactionId(int $orderId, string $transactionId): void
    {
        // Your database logic
    }
}
```

---

## 2. Hotel Reservation with Pre-Authorization

Pre-authorize payment on booking, capture on check-in.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

class HotelBookingService
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: false
        );
    }

    /**
     * Step 1: Pre-authorize payment when booking is made
     */
    public function createReservation(array $booking, array $guest): array
    {
        $bookingRef = 'BOOK_' . date('Ymd') . '_' . uniqid();

        // Pre-authorization request
        $request = new PaymentRequest(
            amount: $booking['total_amount'],
            uniqueReference: $bookingRef,
            transactionType: TransactionType::AUTH, // Pre-authorize
            customerEmail: $guest['email'],
            customerFirstName: $guest['first_name'],
            customerLastName: $guest['last_name'],
            customerPhone: $guest['phone'],
            description: "Hotel Reservation - {$booking['room_type']}",
            redirectUrl: "https://hotel.com/booking/confirmed?ref={$bookingRef}",
            callbackUrl: "https://hotel.com/webhooks/gpg"
        );

        // Add booking details
        $request->setUdfField(1, "Room: {$booking['room_number']}")
                ->setUdfField(2, "Check-in: {$booking['check_in']}")
                ->setUdfField(3, "Check-out: {$booking['check_out']}")
                ->setUdfField(4, "Nights: {$booking['nights']}")
                ->setUdfField(5, "Guest ID: {$guest['id']}");

        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                // Save booking with transaction ID
                $bookingId = $this->saveBooking($bookingRef, $booking, $guest, [
                    'transaction_id' => $response->getTransactionId(),
                    'status' => 'pending_payment'
                ]);

                return [
                    'success' => true,
                    'booking_id' => $bookingId,
                    'payment_url' => $this->gpgClient->buildPaymentPageUrl(
                        $response->getTransactionId()
                    )
                ];
            }
        } catch (\Exception $e) {
            error_log("Booking payment failed: " . $e->getMessage());
        }

        return ['success' => false];
    }

    /**
     * Step 2: Capture payment when guest checks in
     */
    public function checkInGuest(int $bookingId, float $additionalCharges = 0.00): bool
    {
        $booking = $this->getBooking($bookingId);

        if (!$booking || !$booking['transaction_id']) {
            return false;
        }

        // Calculate final amount (original + incidentals)
        $finalAmount = $booking['original_amount'] + $additionalCharges;

        try {
            $response = $this->gpgClient->capturePayment(
                transactionId: $booking['transaction_id'],
                amount: $finalAmount
            );

            if ($response->isSuccess()) {
                // Update booking status
                $this->updateBookingStatus($bookingId, 'checked_in', [
                    'captured_amount' => $finalAmount,
                    'additional_charges' => $additionalCharges,
                    'captured_at' => date('Y-m-d H:i:s')
                ]);

                // Send confirmation email
                $this->sendCheckInConfirmation($booking['guest_email'], $finalAmount);

                return true;
            }
        } catch (\Exception $e) {
            error_log("Payment capture failed: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Cancel reservation and release pre-authorized funds
     */
    public function cancelReservation(int $bookingId): bool
    {
        $booking = $this->getBooking($bookingId);

        if (!$booking || !$booking['transaction_id']) {
            return false;
        }

        try {
            $response = $this->gpgClient->voidPayment($booking['transaction_id']);

            if ($response->isSuccess()) {
                $this->updateBookingStatus($bookingId, 'cancelled');
                $this->sendCancellationEmail($booking['guest_email']);
                return true;
            }
        } catch (\Exception $e) {
            error_log("Cancellation failed: " . $e->getMessage());
        }

        return false;
    }

    private function saveBooking(string $ref, array $booking, array $guest, array $payment): int
    {
        // Database logic
        return 1;
    }

    private function getBooking(int $id): ?array
    {
        // Database logic
        return [];
    }

    private function updateBookingStatus(int $id, string $status, array $data = []): void
    {
        // Database logic
    }

    private function sendCheckInConfirmation(string $email, float $amount): void
    {
        // Email logic
    }

    private function sendCancellationEmail(string $email): void
    {
        // Email logic
    }
}
```

---

## 3. Subscription Payment

Process recurring subscription payments.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

class SubscriptionService
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: false
        );
    }

    /**
     * Process monthly subscription payment
     */
    public function processMonthlyBilling(int $subscriptionId): bool
    {
        $subscription = $this->getSubscription($subscriptionId);
        $customer = $this->getCustomer($subscription['customer_id']);

        // Generate unique reference for this billing cycle
        $billingRef = sprintf(
            'SUB_%d_%s',
            $subscriptionId,
            date('Y_m')
        );

        $request = new PaymentRequest(
            amount: $subscription['amount'],
            uniqueReference: $billingRef,
            transactionType: TransactionType::SALE,
            customerEmail: $customer['email'],
            customerFirstName: $customer['first_name'],
            customerLastName: $customer['last_name'],
            description: "{$subscription['plan_name']} - " . date('F Y'),
            redirectUrl: "https://app.com/subscription/payment-complete",
            callbackUrl: "https://app.com/webhooks/gpg"
        );

        // Add subscription metadata
        $request->setUdfField(1, "Subscription ID: {$subscriptionId}")
                ->setUdfField(2, "Plan: {$subscription['plan_name']}")
                ->setUdfField(3, "Billing Period: " . date('Y-m'))
                ->setUdfField(4, "Customer ID: {$customer['id']}")
                ->setUdfField(5, "Cycle: {$subscription['billing_cycle']}");

        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                // Create billing record
                $this->createBillingRecord($subscriptionId, [
                    'transaction_id' => $response->getTransactionId(),
                    'amount' => $subscription['amount'],
                    'billing_date' => date('Y-m-d'),
                    'status' => 'pending',
                    'reference' => $billingRef
                ]);

                // Send payment request email
                $this->sendPaymentRequestEmail(
                    $customer['email'],
                    $this->gpgClient->buildPaymentPageUrl($response->getTransactionId())
                );

                return true;
            } else {
                // Handle payment creation failure
                $this->handleBillingFailure($subscriptionId, $response->getMessage());
            }
        } catch (\Exception $e) {
            error_log("Subscription billing failed: " . $e->getMessage());
            $this->handleBillingFailure($subscriptionId, $e->getMessage());
        }

        return false;
    }

    /**
     * Handle failed subscription payment
     */
    private function handleBillingFailure(int $subscriptionId, string $reason): void
    {
        $subscription = $this->getSubscription($subscriptionId);

        // Increment failure count
        $this->incrementFailureCount($subscriptionId);

        // Check if max retries exceeded
        if ($subscription['failure_count'] >= 3) {
            // Suspend subscription
            $this->updateSubscriptionStatus($subscriptionId, 'suspended');

            // Notify customer
            $this->sendSubscriptionSuspendedEmail($subscription['customer_id']);
        } else {
            // Schedule retry in 3 days
            $this->scheduleRetry($subscriptionId, '+3 days');

            // Send reminder email
            $this->sendPaymentFailureEmail($subscription['customer_id'], $reason);
        }
    }

    private function getSubscription(int $id): array
    {
        // Database logic
        return [];
    }

    private function getCustomer(int $id): array
    {
        // Database logic
        return [];
    }

    private function createBillingRecord(int $subscriptionId, array $data): void
    {
        // Database logic
    }

    private function incrementFailureCount(int $subscriptionId): void
    {
        // Database logic
    }

    private function updateSubscriptionStatus(int $id, string $status): void
    {
        // Database logic
    }

    private function scheduleRetry(int $id, string $delay): void
    {
        // Queue/scheduler logic
    }

    private function sendPaymentRequestEmail(string $email, string $paymentUrl): void
    {
        // Email logic
    }

    private function sendPaymentFailureEmail(int $customerId, string $reason): void
    {
        // Email logic
    }

    private function sendSubscriptionSuspendedEmail(int $customerId): void
    {
        // Email logic
    }
}
```

---

## 4. Marketplace Split Payment

Track marketplace transactions with vendor commission.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

class MarketplacePaymentService
{
    private GpgClient $gpgClient;
    private float $platformCommission = 0.15; // 15% commission

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: false
        );
    }

    public function processMarketplaceSale(array $order): array
    {
        $vendor = $this->getVendor($order['vendor_id']);
        $customer = $this->getCustomer($order['customer_id']);

        // Calculate splits
        $totalAmount = $order['amount'];
        $commission = $totalAmount * $this->platformCommission;
        $vendorAmount = $totalAmount - $commission;

        $orderRef = 'MKT_' . time() . '_' . uniqid();

        $request = new PaymentRequest(
            amount: $totalAmount,
            uniqueReference: $orderRef,
            transactionType: TransactionType::SALE,
            customerEmail: $customer['email'],
            customerFirstName: $customer['first_name'],
            customerLastName: $customer['last_name'],
            description: "Marketplace Purchase - {$vendor['shop_name']}",
            redirectUrl: "https://marketplace.com/order/success?ref={$orderRef}",
            callbackUrl: "https://marketplace.com/webhooks/gpg"
        );

        // Store split information in UDF fields
        $request->setUdfField(1, "Vendor ID: {$vendor['id']}")
                ->setUdfField(2, "Vendor Amount: " . number_format($vendorAmount, 2))
                ->setUdfField(3, "Commission: " . number_format($commission, 2))
                ->setUdfField(4, "Order ID: {$order['id']}")
                ->setUdfField(5, "Customer ID: {$customer['id']}");

        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                // Store transaction with split details
                $this->createMarketplaceTransaction($order['id'], [
                    'transaction_id' => $response->getTransactionId(),
                    'total_amount' => $totalAmount,
                    'vendor_amount' => $vendorAmount,
                    'commission_amount' => $commission,
                    'vendor_id' => $vendor['id'],
                    'customer_id' => $customer['id'],
                    'status' => 'pending'
                ]);

                return [
                    'success' => true,
                    'payment_url' => $this->gpgClient->buildPaymentPageUrl(
                        $response->getTransactionId()
                    ),
                    'transaction_id' => $response->getTransactionId()
                ];
            }
        } catch (\Exception $e) {
            error_log("Marketplace payment failed: " . $e->getMessage());
        }

        return ['success' => false];
    }

    /**
     * Generate vendor payout report
     */
    public function generateVendorPayoutReport(int $vendorId, string $period): array
    {
        $transactions = $this->getVendorTransactions($vendorId, $period);

        $report = [
            'vendor_id' => $vendorId,
            'period' => $period,
            'total_sales' => 0,
            'total_commission' => 0,
            'net_payout' => 0,
            'transaction_count' => count($transactions),
            'transactions' => []
        ];

        foreach ($transactions as $tx) {
            $report['total_sales'] += $tx['total_amount'];
            $report['total_commission'] += $tx['commission_amount'];
            $report['net_payout'] += $tx['vendor_amount'];

            $report['transactions'][] = [
                'date' => $tx['created_at'],
                'order_id' => $tx['order_id'],
                'amount' => $tx['total_amount'],
                'your_share' => $tx['vendor_amount'],
                'commission' => $tx['commission_amount']
            ];
        }

        return $report;
    }

    private function getVendor(int $id): array
    {
        return [];
    }

    private function getCustomer(int $id): array
    {
        return [];
    }

    private function createMarketplaceTransaction(int $orderId, array $data): void
    {
        // Database logic
    }

    private function getVendorTransactions(int $vendorId, string $period): array
    {
        // Database logic
        return [];
    }
}
```

---

## 5. Government Service Payment

Process government service fees (licenses, permits, fines).

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;

class GovernmentServicePaymentService
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: false
        );
    }

    /**
     * Process license renewal payment
     */
    public function processLicenseRenewal(array $application): array
    {
        $applicant = $this->getApplicant($application['applicant_id']);

        $applicationRef = sprintf(
            'LIC_%s_%s',
            $application['license_type'],
            uniqid()
        );

        $request = new PaymentRequest(
            amount: $application['fee_amount'],
            uniqueReference: $applicationRef,
            transactionType: TransactionType::SALE,
            customerEmail: $applicant['email'],
            customerFirstName: $applicant['first_name'],
            customerLastName: $applicant['last_name'],
            customerPhone: $applicant['phone'],
            description: "License Renewal - {$application['license_type']}",
            redirectUrl: "https://gov.mt/services/license/success?ref={$applicationRef}",
            callbackUrl: "https://gov.mt/webhooks/gpg"
        );

        // Add application details
        $request->setUdfField(1, "Application ID: {$application['id']}")
                ->setUdfField(2, "License Type: {$application['license_type']}")
                ->setUdfField(3, "License Number: {$application['license_number']}")
                ->setUdfField(4, "ID Card: {$applicant['id_card']}")
                ->setUdfField(5, "Department: {$application['department']}");

        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                // Update application status
                $this->updateApplicationStatus($application['id'], 'payment_pending', [
                    'transaction_id' => $response->getTransactionId(),
                    'payment_reference' => $applicationRef
                ]);

                // Log for audit trail
                $this->logAuditEvent('license_payment_initiated', [
                    'application_id' => $application['id'],
                    'applicant_id' => $applicant['id'],
                    'amount' => $application['fee_amount'],
                    'transaction_id' => $response->getTransactionId()
                ]);

                return [
                    'success' => true,
                    'payment_url' => $this->gpgClient->buildPaymentPageUrl(
                        $response->getTransactionId()
                    ),
                    'reference' => $applicationRef
                ];
            }
        } catch (\Exception $e) {
            error_log("Government payment failed: " . $e->getMessage());
            $this->logAuditEvent('license_payment_failed', [
                'application_id' => $application['id'],
                'error' => $e->getMessage()
            ]);
        }

        return ['success' => false];
    }

    /**
     * Process traffic fine payment
     */
    public function processFinePayment(array $fine): array
    {
        $offender = $this->getOffender($fine['offender_id']);

        $fineRef = sprintf('FINE_%s_%s', $fine['ticket_number'], uniqid());

        $request = new PaymentRequest(
            amount: $fine['amount'],
            uniqueReference: $fineRef,
            transactionType: TransactionType::SALE,
            customerEmail: $offender['email'],
            customerFirstName: $offender['first_name'],
            customerLastName: $offender['last_name'],
            description: "Traffic Fine - Ticket #{$fine['ticket_number']}",
            redirectUrl: "https://gov.mt/fines/paid?ref={$fineRef}",
            callbackUrl: "https://gov.mt/webhooks/gpg"
        );

        $request->setUdfField(1, "Ticket: {$fine['ticket_number']}")
                ->setUdfField(2, "Offense: {$fine['offense_type']}")
                ->setUdfField(3, "Date: {$fine['offense_date']}")
                ->setUdfField(4, "Vehicle: {$fine['vehicle_plate']}")
                ->setUdfField(5, "ID: {$offender['id_card']}");

        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                $this->updateFineStatus($fine['id'], 'payment_pending', [
                    'transaction_id' => $response->getTransactionId()
                ]);

                return [
                    'success' => true,
                    'payment_url' => $this->gpgClient->buildPaymentPageUrl(
                        $response->getTransactionId()
                    )
                ];
            }
        } catch (\Exception $e) {
            error_log("Fine payment failed: " . $e->getMessage());
        }

        return ['success' => false];
    }

    private function getApplicant(int $id): array
    {
        return [];
    }

    private function getOffender(int $id): array
    {
        return [];
    }

    private function updateApplicationStatus(int $id, string $status, array $data): void
    {
        // Database logic
    }

    private function updateFineStatus(int $id, string $status, array $data): void
    {
        // Database logic
    }

    private function logAuditEvent(string $event, array $data): void
    {
        // Audit logging logic
    }
}
```

---

## 6. Refund Processing

Handle various refund scenarios.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;

class RefundService
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: false
        );
    }

    /**
     * Process full refund
     */
    public function processFullRefund(int $orderId, string $reason): bool
    {
        $order = $this->getOrder($orderId);

        if (!$order || $order['status'] !== 'paid') {
            return false;
        }

        try {
            $response = $this->gpgClient->refundPayment(
                transactionId: $order['transaction_id']
            );

            if ($response->isSuccess()) {
                // Update order status
                $this->updateOrderStatus($orderId, 'refunded', [
                    'refund_transaction_id' => $response->getTransactionId(),
                    'refund_amount' => $order['amount'],
                    'refund_reason' => $reason,
                    'refunded_at' => date('Y-m-d H:i:s')
                ]);

                // Notify customer
                $this->sendRefundConfirmation(
                    $order['customer_email'],
                    $order['amount'],
                    $reason
                );

                // Log for accounting
                $this->logRefund($orderId, $order['amount'], $reason);

                return true;
            }
        } catch (\Exception $e) {
            error_log("Refund failed: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Process partial refund
     */
    public function processPartialRefund(
        int $orderId,
        float $refundAmount,
        string $reason
    ): bool {
        $order = $this->getOrder($orderId);

        if (!$order || $order['status'] !== 'paid') {
            return false;
        }

        // Validate refund amount
        if ($refundAmount > $order['amount']) {
            throw new \InvalidArgumentException('Refund amount exceeds order total');
        }

        try {
            $response = $this->gpgClient->refundPayment(
                transactionId: $order['transaction_id'],
                amount: $refundAmount
            );

            if ($response->isSuccess()) {
                // Update order with partial refund
                $this->updateOrderStatus($orderId, 'partially_refunded', [
                    'refund_transaction_id' => $response->getTransactionId(),
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $reason,
                    'remaining_amount' => $order['amount'] - $refundAmount,
                    'refunded_at' => date('Y-m-d H:i:s')
                ]);

                $this->sendRefundConfirmation(
                    $order['customer_email'],
                    $refundAmount,
                    $reason
                );

                return true;
            }
        } catch (\Exception $e) {
            error_log("Partial refund failed: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Bulk refund processing (e.g., event cancellation)
     */
    public function processBulkRefunds(array $orderIds, string $reason): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'total_refunded' => 0
        ];

        foreach ($orderIds as $orderId) {
            try {
                if ($this->processFullRefund($orderId, $reason)) {
                    $order = $this->getOrder($orderId);
                    $results['success'][] = $orderId;
                    $results['total_refunded'] += $order['amount'];
                } else {
                    $results['failed'][] = $orderId;
                }
            } catch (\Exception $e) {
                $results['failed'][] = $orderId;
                error_log("Bulk refund failed for order {$orderId}: " . $e->getMessage());
            }

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        return $results;
    }

    private function getOrder(int $id): ?array
    {
        return [];
    }

    private function updateOrderStatus(int $id, string $status, array $data): void
    {
        // Database logic
    }

    private function sendRefundConfirmation(string $email, float $amount, string $reason): void
    {
        // Email logic
    }

    private function logRefund(int $orderId, float $amount, string $reason): void
    {
        // Accounting log
    }
}
```

---

## 7. Webhook Handler

Comprehensive webhook processing with signature verification.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\Exceptions\InvalidSignatureException;

class GpgWebhookController
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: $_ENV['GPG_TEST_MODE'] === 'true'
        );
    }

    public function handleWebhook(): void
    {
        // Get raw payload
        $payload = file_get_contents('php://input');

        // Get signature from header
        $signature = $_SERVER['HTTP_X_GPG_SIGNATURE'] ?? null;
        $secret = $_ENV['GPG_WEBHOOK_SECRET'];

        // Log webhook receipt
        $this->logWebhook($payload, $signature);

        try {
            // Parse and verify webhook
            $webhook = $this->gpgClient->parseWebhook($payload, $signature, $secret);

            // Process based on transaction status
            switch (true) {
                case $webhook->isProcessed():
                    $this->handleSuccessfulPayment($webhook);
                    break;

                case $webhook->isDeclined():
                    $this->handleDeclinedPayment($webhook);
                    break;

                case $webhook->isPending():
                    $this->handlePendingPayment($webhook);
                    break;
            }

            // Always acknowledge receipt
            http_response_code(200);
            echo json_encode(['status' => 'ok']);

        } catch (InvalidSignatureException $e) {
            // Security issue - invalid signature
            error_log("Invalid webhook signature: " . $e->getMessage());
            $this->alertSecurity('Invalid webhook signature received');

            http_response_code(403);
            echo json_encode(['error' => 'Invalid signature']);

        } catch (\Exception $e) {
            // Processing error - log but acknowledge
            error_log("Webhook processing error: " . $e->getMessage());

            // Still acknowledge to prevent retries for processing errors
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function handleSuccessfulPayment($webhook): void
    {
        $transactionId = $webhook->getTransactionId();
        $orderRef = $webhook->getUniqueReference();
        $amount = $webhook->getAmount();

        // Find order by reference
        $order = $this->findOrderByReference($orderRef);

        if (!$order) {
            error_log("Order not found for reference: {$orderRef}");
            return;
        }

        // Prevent duplicate processing
        if ($order['status'] === 'paid') {
            error_log("Order {$order['id']} already marked as paid");
            return;
        }

        // Verify amount matches
        if (abs($amount - $order['amount']) > 0.01) {
            error_log("Amount mismatch for order {$order['id']}: expected {$order['amount']}, got {$amount}");
            $this->alertFinance('Payment amount mismatch', [
                'order_id' => $order['id'],
                'expected' => $order['amount'],
                'received' => $amount
            ]);
            return;
        }

        // Update order
        $this->updateOrderStatus($order['id'], 'paid', [
            'transaction_id' => $transactionId,
            'gateway_id' => $webhook->getGatewayId(),
            'paid_amount' => $amount,
            'payment_method' => $webhook->getCardScheme(),
            'card_number' => $webhook->getCardNumber(),
            'auth_code' => $webhook->getAuthCode(),
            'paid_at' => date('Y-m-d H:i:s')
        ]);

        // Process order fulfillment
        $this->processOrderFulfillment($order['id']);

        // Send confirmation email
        $this->sendOrderConfirmation($order['customer_email'], $order['id']);

        // Trigger invoice generation
        $this->generateInvoice($order['id']);
    }

    private function handleDeclinedPayment($webhook): void
    {
        $orderRef = $webhook->getUniqueReference();
        $order = $this->findOrderByReference($orderRef);

        if (!$order) {
            return;
        }

        $this->updateOrderStatus($order['id'], 'payment_failed', [
            'transaction_id' => $webhook->getTransactionId(),
            'decline_reason' => $webhook->getBankResponse(),
            'failed_at' => date('Y-m-d H:i:s')
        ]);

        // Send failure notification
        $this->sendPaymentFailureEmail(
            $order['customer_email'],
            $webhook->getBankResponse()
        );
    }

    private function handlePendingPayment($webhook): void
    {
        $orderRef = $webhook->getUniqueReference();
        $order = $this->findOrderByReference($orderRef);

        if (!$order) {
            return;
        }

        $this->updateOrderStatus($order['id'], 'payment_pending', [
            'transaction_id' => $webhook->getTransactionId()
        ]);
    }

    private function logWebhook(string $payload, ?string $signature): void
    {
        // Log to database or file
        file_put_contents(
            '/var/log/gpg_webhooks.log',
            date('Y-m-d H:i:s') . " | Signature: {$signature} | Payload: {$payload}\n",
            FILE_APPEND
        );
    }

    private function findOrderByReference(string $ref): ?array
    {
        // Database query
        return [];
    }

    private function updateOrderStatus(int $id, string $status, array $data): void
    {
        // Database update
    }

    private function processOrderFulfillment(int $orderId): void
    {
        // Fulfillment logic
    }

    private function sendOrderConfirmation(string $email, int $orderId): void
    {
        // Email logic
    }

    private function sendPaymentFailureEmail(string $email, string $reason): void
    {
        // Email logic
    }

    private function generateInvoice(int $orderId): void
    {
        // Invoice generation
    }

    private function alertSecurity(string $message, array $context = []): void
    {
        // Security alert
    }

    private function alertFinance(string $message, array $context = []): void
    {
        // Finance alert
    }
}
```

---

## 8. Transaction Reconciliation

Daily reconciliation between local database and GPG.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;

class ReconciliationService
{
    private GpgClient $gpgClient;

    public function __construct()
    {
        $this->gpgClient = new GpgClient(
            apiKey: $_ENV['GPG_API_KEY'],
            testMode: false
        );
    }

    /**
     * Reconcile transactions for a specific date
     */
    public function reconcileDate(string $date): array
    {
        // Get transactions from GPG
        $gpgTransactions = $this->gpgClient->getTransactions([
            'startDate' => $date,
            'endDate' => $date,
            'pageSize' => 1000
        ]);

        // Get local transactions
        $localTransactions = $this->getLocalTransactions($date);

        $report = [
            'date' => $date,
            'gpg_count' => count($gpgTransactions['result']['transactions'] ?? []),
            'local_count' => count($localTransactions),
            'matched' => [],
            'missing_locally' => [],
            'missing_in_gpg' => [],
            'amount_mismatches' => [],
            'status_mismatches' => []
        ];

        // Create lookup maps
        $gpgMap = [];
        foreach ($gpgTransactions['result']['transactions'] ?? [] as $tx) {
            $gpgMap[$tx['transactionId']] = $tx;
        }

        $localMap = [];
        foreach ($localTransactions as $tx) {
            $localMap[$tx['transaction_id']] = $tx;
        }

        // Check local against GPG
        foreach ($localTransactions as $local) {
            $txId = $local['transaction_id'];

            if (!isset($gpgMap[$txId])) {
                $report['missing_in_gpg'][] = $local;
                continue;
            }

            $gpg = $gpgMap[$txId];

            // Check amount
            if (abs($gpg['amount'] - $local['amount']) > 0.01) {
                $report['amount_mismatches'][] = [
                    'transaction_id' => $txId,
                    'local_amount' => $local['amount'],
                    'gpg_amount' => $gpg['amount']
                ];
            }

            // Check status
            if ($gpg['status'] !== $local['status']) {
                $report['status_mismatches'][] = [
                    'transaction_id' => $txId,
                    'local_status' => $local['status'],
                    'gpg_status' => $gpg['status']
                ];

                // Auto-correct status
                $this->updateLocalTransactionStatus($local['id'], $gpg['status']);
            }

            $report['matched'][] = $txId;
        }

        // Check GPG against local
        foreach ($gpgMap as $txId => $gpg) {
            if (!isset($localMap[$txId])) {
                $report['missing_locally'][] = $gpg;

                // Create missing transaction
                $this->createMissingTransaction($gpg);
            }
        }

        // Generate reconciliation report
        $this->saveReconciliationReport($report);

        // Alert if discrepancies found
        if (
            !empty($report['missing_locally']) ||
            !empty($report['missing_in_gpg']) ||
            !empty($report['amount_mismatches'])
        ) {
            $this->alertFinance('Reconciliation discrepancies found', $report);
        }

        return $report;
    }

    /**
     * Export reconciliation report to CSV
     */
    public function exportReconciliationReport(string $date): string
    {
        $report = $this->reconcileDate($date);

        $csv = "Transaction ID,Status,Amount,Discrepancy Type\n";

        foreach ($report['missing_locally'] as $tx) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%.2f\",\"Missing Locally\"\n",
                $tx['transactionId'],
                $tx['status'],
                $tx['amount']
            );
        }

        foreach ($report['missing_in_gpg'] as $tx) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%.2f\",\"Missing in GPG\"\n",
                $tx['transaction_id'],
                $tx['status'],
                $tx['amount']
            );
        }

        foreach ($report['amount_mismatches'] as $mismatch) {
            $csv .= sprintf(
                "\"%s\",\"MISMATCH\",\"%.2f vs %.2f\",\"Amount Mismatch\"\n",
                $mismatch['transaction_id'],
                $mismatch['local_amount'],
                $mismatch['gpg_amount']
            );
        }

        return $csv;
    }

    private function getLocalTransactions(string $date): array
    {
        // Database query
        return [];
    }

    private function updateLocalTransactionStatus(int $id, string $status): void
    {
        // Database update
    }

    private function createMissingTransaction(array $gpgTransaction): void
    {
        // Database insert
    }

    private function saveReconciliationReport(array $report): void
    {
        // Save to database
    }

    private function alertFinance(string $message, array $context): void
    {
        // Alert logic
    }
}
```

---

## 9. Error Handling Patterns

Best practices for handling errors.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\Exceptions\{
    AuthenticationException,
    ValidationException,
    ApiException,
    NetworkException
};

class PaymentService
{
    private GpgClient $gpgClient;
    private LoggerInterface $logger;

    public function processPayment(PaymentRequest $request): array
    {
        try {
            $response = $this->gpgClient->createPayment($request);

            if ($response->isSuccess()) {
                return [
                    'success' => true,
                    'transaction_id' => $response->getTransactionId(),
                    'payment_url' => $this->gpgClient->buildPaymentPageUrl(
                        $response->getTransactionId()
                    )
                ];
            } else {
                // API returned success=false
                $this->logger->warning('Payment creation returned false', [
                    'message' => $response->getMessage(),
                    'response' => $response->getRawResponse()
                ]);

                return [
                    'success' => false,
                    'error' => 'Payment could not be initiated',
                    'user_message' => 'Unable to process payment. Please try again.'
                ];
            }

        } catch (AuthenticationException $e) {
            // Critical: API key is invalid or expired
            $this->logger->critical('GPG authentication failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            // Alert development team
            $this->alertDevelopment('GPG Authentication Failure');

            return [
                'success' => false,
                'error' => 'authentication_failed',
                'user_message' => 'System configuration error. Please contact support.'
            ];

        } catch (ValidationException $e) {
            // Request data is invalid
            $this->logger->error('Payment validation failed', [
                'errors' => $e->getErrors(),
                'context' => $e->getContext()
            ]);

            return [
                'success' => false,
                'error' => 'validation_failed',
                'errors' => $e->getErrors(),
                'user_message' => 'Invalid payment details. Please check and try again.'
            ];

        } catch (NetworkException $e) {
            // Connection or network error
            $this->logger->error('Network error during payment', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'network_error',
                'user_message' => 'Service temporarily unavailable. Please try again in a few moments.',
                'retry' => true
            ];

        } catch (ApiException $e) {
            // General API error
            $statusCode = $e->getStatusCode();

            $this->logger->error('GPG API error', [
                'status_code' => $statusCode,
                'message' => $e->getMessage(),
                'response' => $e->getResponseBody()
            ]);

            // Check if retriable
            $retriable = $statusCode >= 500 && $statusCode < 600;

            return [
                'success' => false,
                'error' => 'api_error',
                'user_message' => $retriable
                    ? 'Temporary service issue. Please try again.'
                    : 'Unable to process payment. Please contact support.',
                'retry' => $retriable
            ];

        } catch (\Exception $e) {
            // Unexpected error
            $this->logger->critical('Unexpected error during payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'unexpected_error',
                'user_message' => 'An unexpected error occurred. Please contact support.'
            ];
        }
    }

    private function alertDevelopment(string $message): void
    {
        // Send alert via Slack, email, PagerDuty, etc.
    }
}
```

---

## 10. Testing Strategies

Testing your GPG integration.

```php
<?php

use BornMT\MitaGpg\Client\GpgClient;
use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\Enums\TransactionType;
use PHPUnit\Framework\TestCase;

class GpgIntegrationTest extends TestCase
{
    private GpgClient $client;

    protected function setUp(): void
    {
        $this->client = new GpgClient(
            apiKey: $_ENV['GPG_TEST_API_KEY'],
            testMode: true
        );
    }

    public function testCreateSimplePayment(): void
    {
        $request = new PaymentRequest(
            amount: 10.00,
            uniqueReference: 'TEST_' . uniqid(),
            transactionType: TransactionType::SALE,
            customerEmail: 'test@example.com',
            description: 'Test Payment'
        );

        $response = $this->client->createPayment($request);

        $this->assertTrue($response->isSuccess());
        $this->assertNotNull($response->getTransactionId());
        $this->assertNotNull($response->getPaymentUrl());
    }

    public function testWebhookSignatureVerification(): void
    {
        $payload = json_encode([
            'transactionId' => 'test123',
            'status' => 'PROCESSED',
            'amount' => 50.00
        ]);

        $secret = 'test_secret';
        $validSignature = hash_hmac('sha256', $payload, $secret);
        $invalidSignature = 'invalid_signature';

        // Test valid signature
        $this->assertTrue(
            $this->client->verifyWebhookSignature($payload, $validSignature, $secret)
        );

        // Test invalid signature
        $this->assertFalse(
            $this->client->verifyWebhookSignature($payload, $invalidSignature, $secret)
        );
    }

    public function testGetTransaction(): void
    {
        // First create a payment
        $request = new PaymentRequest(
            amount: 15.00,
            uniqueReference: 'TEST_' . uniqid()
        );

        $response = $this->client->createPayment($request);
        $transactionId = $response->getTransactionId();

        // Then retrieve it
        $transaction = $this->client->getTransaction($transactionId);

        $this->assertIsArray($transaction);
        $this->assertEquals($transactionId, $transaction['result']['transactionId']);
    }

    public function testInvalidApiKeyThrowsException(): void
    {
        $this->expectException(\BornMT\MitaGpg\Exceptions\AuthenticationException::class);

        $invalidClient = new GpgClient(
            apiKey: 'invalid_key',
            testMode: true
        );

        $request = new PaymentRequest(
            amount: 10.00,
            uniqueReference: 'TEST_' . uniqid()
        );

        $invalidClient->createPayment($request);
    }
}
```

---

## Additional Tips

### Using with Dependency Injection

```php
<?php

// In your IoC container
$container->singleton(GpgClient::class, function () {
    return new GpgClient(
        apiKey: $_ENV['GPG_API_KEY'],
        testMode: $_ENV['APP_ENV'] !== 'production'
    );
});

// In your service
class OrderService
{
    public function __construct(private GpgClient $gpgClient)
    {
    }

    public function checkout(array $cart): array
    {
        // Use $this->gpgClient
    }
}
```

### Logging Best Practices

```php
<?php

// Log all payment attempts
$this->logger->info('Payment initiated', [
    'amount' => $request->getAmount(),
    'reference' => $request->getUniqueReference(),
    'customer' => $customer['email']
]);

// Log responses
$this->logger->info('Payment response', [
    'success' => $response->isSuccess(),
    'transaction_id' => $response->getTransactionId(),
    'status' => $response->getStatus()?->value
]);

// Never log sensitive data
// ❌ Don't log: card numbers, CVV, full API keys
// ✅ Do log: transaction IDs, status, amounts
```

---

For more examples and documentation, visit:
- **API Documentation**: https://gpgapi.redoc.ly/
- **Package Repository**: https://github.com/born-mt/mita-gpg-sdk