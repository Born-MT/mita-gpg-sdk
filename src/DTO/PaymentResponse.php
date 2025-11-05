<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\DTO;

use BornMT\MitaGpg\Enums\TransactionStatus;

/**
 * Payment Response DTO
 */
class PaymentResponse
{
    public function __construct(
        private bool $success,
        private ?string $transactionId = null,
        private ?string $gatewayId = null,
        private ?TransactionStatus $status = null,
        private ?string $paymentUrl = null,
        private ?string $message = null,
        private ?string $processId = null,
        private ?string $dateTime = null,
        private array $rawResponse = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $result = $data['result'] ?? [];

        return new self(
            success: $data['success'] ?? false,
            transactionId: $result['transactionId'] ?? null,
            gatewayId: $result['gatewayId'] ?? null,
            status: isset($result['status']) ? TransactionStatus::from($result['status']) : null,
            paymentUrl: $result['paymentUrl'] ?? null,
            message: $data['message'] ?? $result['message'] ?? null,
            processId: $data['processId'] ?? null,
            dateTime: $data['dateTime'] ?? null,
            rawResponse: $data
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    public function getStatus(): ?TransactionStatus
    {
        return $this->status;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getProcessId(): ?string
    {
        return $this->processId;
    }

    public function getDateTime(): ?string
    {
        return $this->dateTime;
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }
}