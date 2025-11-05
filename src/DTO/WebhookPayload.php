<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\DTO;

use BornMT\MitaGpg\Enums\TransactionStatus;
use BornMT\MitaGpg\Enums\TransactionType;

/**
 * Webhook Payload DTO
 */
class WebhookPayload
{
    public function __construct(
        private string $transactionId,
        private string $gatewayId,
        private TransactionStatus $status,
        private TransactionType $transactionType,
        private float $amount,
        private string $currency,
        private ?string $authCode = null,
        private ?string $cardNumber = null,
        private ?string $cardScheme = null,
        private ?string $bankResponse = null,
        private ?string $uniqueReference = null,
        private ?string $customerEmail = null,
        private ?array $threeDSecure = null,
        private ?array $udfFields = null,
        private ?string $processedAt = null,
        private array $rawPayload = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            transactionId: $data['transactionId'] ?? $data['TransactionId'] ?? '',
            gatewayId: $data['gatewayId'] ?? $data['GatewayId'] ?? '',
            status: TransactionStatus::from($data['status'] ?? $data['Status'] ?? 'PENDING'),
            transactionType: TransactionType::from($data['transactionType'] ?? $data['TransactionType'] ?? 'SALE'),
            amount: (float) ($data['amount'] ?? $data['Amount'] ?? 0),
            currency: $data['currency'] ?? $data['Currency'] ?? 'EUR',
            authCode: $data['authCode'] ?? $data['AuthCode'] ?? null,
            cardNumber: $data['cardNumber'] ?? $data['CardNumber'] ?? null,
            cardScheme: $data['cardScheme'] ?? $data['CardScheme'] ?? null,
            bankResponse: $data['bankResponse'] ?? $data['BankResponse'] ?? null,
            uniqueReference: $data['uniqueReference'] ?? $data['UniqueReference'] ?? null,
            customerEmail: $data['customerEmail'] ?? $data['CustomerEmail'] ?? null,
            threeDSecure: $data['threeDSecure'] ?? $data['ThreeDSecure'] ?? null,
            udfFields: [
                'udf1' => $data['udf1'] ?? $data['UDF1'] ?? null,
                'udf2' => $data['udf2'] ?? $data['UDF2'] ?? null,
                'udf3' => $data['udf3'] ?? $data['UDF3'] ?? null,
                'udf4' => $data['udf4'] ?? $data['UDF4'] ?? null,
                'udf5' => $data['udf5'] ?? $data['UDF5'] ?? null,
            ],
            processedAt: $data['processedAt'] ?? $data['ProcessedAt'] ?? null,
            rawPayload: $data
        );
    }

    public function isProcessed(): bool
    {
        return $this->status === TransactionStatus::PROCESSED;
    }

    public function isDeclined(): bool
    {
        return $this->status === TransactionStatus::DECLINED;
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::PENDING;
    }

    // Getters
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getGatewayId(): string
    {
        return $this->gatewayId;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function getTransactionType(): TransactionType
    {
        return $this->transactionType;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function getCardScheme(): ?string
    {
        return $this->cardScheme;
    }

    public function getBankResponse(): ?string
    {
        return $this->bankResponse;
    }

    public function getUniqueReference(): ?string
    {
        return $this->uniqueReference;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getThreeDSecure(): ?array
    {
        return $this->threeDSecure;
    }

    public function getUdfFields(): ?array
    {
        return $this->udfFields;
    }

    public function getUdfField(int $fieldNumber): ?string
    {
        return $this->udfFields["udf{$fieldNumber}"] ?? null;
    }

    public function getProcessedAt(): ?string
    {
        return $this->processedAt;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }
}