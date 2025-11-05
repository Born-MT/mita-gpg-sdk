<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Client;

use BornMT\MitaGpg\DTO\PaymentRequest;
use BornMT\MitaGpg\DTO\PaymentResponse;
use BornMT\MitaGpg\DTO\TransactionRequest;
use BornMT\MitaGpg\DTO\WebhookPayload;
use BornMT\MitaGpg\Enums\TransactionType;
use BornMT\MitaGpg\Exceptions\ApiException;
use BornMT\MitaGpg\Exceptions\AuthenticationException;
use BornMT\MitaGpg\Exceptions\InvalidSignatureException;
use BornMT\MitaGpg\Exceptions\NetworkException;
use BornMT\MitaGpg\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

/**
 * Malta Government Payment Gateway (GPG) API Client
 *
 * @see https://gpgapi.redoc.ly/
 */
class GpgClient
{
    private const API_BASE_URL = 'https://gpgapi.apcopay.com/api';
    private const HPP_BASE_URL = 'https://gpg.apcopay.com/pay';

    private Client $httpClient;

    public function __construct(
        private string $apiKey,
        private bool $testMode = false,
        private int $timeout = 30,
        private array $options = []
    ) {
        $this->httpClient = new Client(array_merge([
            'base_uri' => self::API_BASE_URL,
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ], $this->options));
    }

    /**
     * Create a Hosted Payment Page transaction
     *
     * @param PaymentRequest $request
     * @return PaymentResponse
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ValidationException
     */
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $response = $this->post('/HostedPaymentPage', $request->toArray());

        return PaymentResponse::fromArray($response);
    }

    /**
     * Capture a pre-authorized payment
     *
     * @param string $transactionId
     * @param float|null $amount Optional amount for partial capture
     * @param string|null $uniqueReference Optional unique reference
     * @return PaymentResponse
     * @throws ApiException
     */
    public function capturePayment(
        string $transactionId,
        ?float $amount = null,
        ?string $uniqueReference = null
    ): PaymentResponse {
        $request = new TransactionRequest(
            transactionId: $transactionId,
            transactionType: TransactionType::CAPTURE,
            amount: $amount,
            uniqueReference: $uniqueReference
        );

        $response = $this->put('/Transaction', $request->toArray());

        return PaymentResponse::fromArray($response);
    }

    /**
     * Refund a processed payment
     *
     * @param string $transactionId
     * @param float|null $amount Optional amount for partial refund
     * @param string|null $uniqueReference Optional unique reference
     * @return PaymentResponse
     * @throws ApiException
     */
    public function refundPayment(
        string $transactionId,
        ?float $amount = null,
        ?string $uniqueReference = null
    ): PaymentResponse {
        $request = new TransactionRequest(
            transactionId: $transactionId,
            transactionType: TransactionType::REFUND,
            amount: $amount,
            uniqueReference: $uniqueReference
        );

        $response = $this->put('/Transaction', $request->toArray());

        return PaymentResponse::fromArray($response);
    }

    /**
     * Void/Cancel a pending or authorized transaction
     *
     * @param string $transactionId
     * @return PaymentResponse
     * @throws ApiException
     */
    public function voidPayment(string $transactionId): PaymentResponse
    {
        $request = new TransactionRequest(
            transactionId: $transactionId,
            transactionType: TransactionType::VOID
        );

        $response = $this->put('/Transaction', $request->toArray());

        return PaymentResponse::fromArray($response);
    }

    /**
     * Get transaction details by ID
     *
     * @param string $transactionId
     * @return array
     * @throws ApiException
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->get("/Transaction/{$transactionId}");
    }

    /**
     * Get multiple transactions with filters
     *
     * @param array $filters
     * @return array
     * @throws ApiException
     */
    public function getTransactions(array $filters = []): array
    {
        return $this->post('/Transactions/GetTransactions', $filters);
    }

    /**
     * Build the Hosted Payment Page URL
     *
     * @param string $transactionId
     * @return string
     */
    public function buildPaymentPageUrl(string $transactionId): string
    {
        return self::HPP_BASE_URL . "/{$transactionId}";
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw webhook payload (JSON string)
     * @param string $signature Signature from webhook headers
     * @param string $secret Webhook secret key
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Parse and validate webhook payload
     *
     * @param string $payload Raw webhook payload
     * @param string|null $signature Optional signature to verify
     * @param string|null $secret Optional secret for verification
     * @return WebhookPayload
     * @throws InvalidSignatureException
     */
    public function parseWebhook(string $payload, ?string $signature = null, ?string $secret = null): WebhookPayload
    {
        // Verify signature if provided
        if ($signature !== null && $secret !== null) {
            if (!$this->verifyWebhookSignature($payload, $signature, $secret)) {
                throw new InvalidSignatureException('Webhook signature verification failed');
            }
        }

        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidSignatureException('Invalid webhook payload: ' . json_last_error_msg());
        }

        return WebhookPayload::fromArray($data);
    }

    /**
     * Execute GET request
     *
     * @param string $endpoint
     * @param array $query
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     */
    private function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->httpClient->get($endpoint, [
                'query' => $query,
            ]);

            return $this->parseResponse($response);
        } catch (ConnectException $e) {
            throw new NetworkException('Connection failed: ' . $e->getMessage(), $e);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (ServerException $e) {
            throw new ApiException(
                'Server error: ' . $e->getMessage(),
                $e->getResponse()->getStatusCode(),
                $e->getResponse()->getBody()->getContents(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Request failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Execute POST request
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ValidationException
     */
    private function post(string $endpoint, array $data): array
    {
        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $data,
            ]);

            return $this->parseResponse($response);
        } catch (ConnectException $e) {
            throw new NetworkException('Connection failed: ' . $e->getMessage(), $e);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (ServerException $e) {
            throw new ApiException(
                'Server error: ' . $e->getMessage(),
                $e->getResponse()->getStatusCode(),
                $e->getResponse()->getBody()->getContents(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Request failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Execute PUT request
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ValidationException
     */
    private function put(string $endpoint, array $data): array
    {
        try {
            $response = $this->httpClient->put($endpoint, [
                'json' => $data,
            ]);

            return $this->parseResponse($response);
        } catch (ConnectException $e) {
            throw new NetworkException('Connection failed: ' . $e->getMessage(), $e);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (ServerException $e) {
            throw new ApiException(
                'Server error: ' . $e->getMessage(),
                $e->getResponse()->getStatusCode(),
                $e->getResponse()->getBody()->getContents(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Request failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Parse API response
     *
     * @param ResponseInterface $response
     * @return array
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                'Invalid JSON response: ' . json_last_error_msg(),
                $response->getStatusCode(),
                $body
            );
        }

        return $data;
    }

    /**
     * Handle client exceptions (4xx errors)
     *
     * @param ClientException $e
     * @throws ApiException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    private function handleClientException(ClientException $e): void
    {
        $statusCode = $e->getResponse()->getStatusCode();
        $body = $e->getResponse()->getBody()->getContents();

        // Try to parse error response
        $errorData = json_decode($body, true);
        $message = $errorData['message'] ?? $e->getMessage();
        $errors = $errorData['errors'] ?? [];

        match ($statusCode) {
            401 => throw new AuthenticationException($message, $statusCode, $e),
            400, 422 => throw new ValidationException($message, $errors, $statusCode, $e),
            default => throw new ApiException($message, $statusCode, $body, $e),
        };
    }

    /**
     * Check if client is in test mode
     *
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Get API key
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get HTTP client instance (for advanced usage)
     *
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }
}