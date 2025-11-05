<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\DTO;

use BornMT\MitaGpg\Enums\TransactionType;

/**
 * Payment Request DTO for creating Hosted Payment Page transactions
 */
class PaymentRequest
{
    public function __construct(
        private float $amount,
        private string $uniqueReference,
        private TransactionType $transactionType = TransactionType::SALE,
        private ?string $customerEmail = null,
        private ?string $customerFirstName = null,
        private ?string $customerLastName = null,
        private ?string $customerPhone = null,
        private ?string $description = null,
        private ?string $redirectUrl = null,
        private ?string $callbackUrl = null,
        private ?string $cancelUrl = null,
        private bool $isTest = false,
        private array $metadata = [],
        private array $udfFields = []
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'Amount' => number_format($this->amount, 2, '.', ''),
            'UniqueReference' => $this->uniqueReference,
            'TransactionType' => $this->transactionType->value,
            'IsTest' => $this->isTest,
        ];

        if ($this->customerEmail !== null) {
            $data['CustomerEmail'] = $this->customerEmail;
        }

        if ($this->customerFirstName !== null) {
            $data['CustomerFirstName'] = $this->customerFirstName;
        }

        if ($this->customerLastName !== null) {
            $data['CustomerLastName'] = $this->customerLastName;
        }

        if ($this->customerPhone !== null) {
            $data['CustomerPhone'] = $this->customerPhone;
        }

        if ($this->description !== null) {
            $data['Description'] = $this->description;
        }

        if ($this->redirectUrl !== null) {
            $data['RedirectUrl'] = $this->redirectUrl;
        }

        if ($this->callbackUrl !== null) {
            $data['CallbackUrl'] = $this->callbackUrl;
        }

        if ($this->cancelUrl !== null) {
            $data['CancelUrl'] = $this->cancelUrl;
        }

        // Add metadata as custom fields
        foreach ($this->metadata as $key => $value) {
            $data[$key] = $value;
        }

        // Add UDF fields (User Defined Fields 1-5)
        if (isset($this->udfFields['udf1'])) {
            $data['UDF1'] = $this->udfFields['udf1'];
        }
        if (isset($this->udfFields['udf2'])) {
            $data['UDF2'] = $this->udfFields['udf2'];
        }
        if (isset($this->udfFields['udf3'])) {
            $data['UDF3'] = $this->udfFields['udf3'];
        }
        if (isset($this->udfFields['udf4'])) {
            $data['UDF4'] = $this->udfFields['udf4'];
        }
        if (isset($this->udfFields['udf5'])) {
            $data['UDF5'] = $this->udfFields['udf5'];
        }

        return $data;
    }

    // Getters
    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getUniqueReference(): string
    {
        return $this->uniqueReference;
    }

    public function getTransactionType(): TransactionType
    {
        return $this->transactionType;
    }

    public function isTest(): bool
    {
        return $this->isTest;
    }

    // Setters for fluent interface
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function setCustomerEmail(string $email): self
    {
        $this->customerEmail = $email;
        return $this;
    }

    public function setCustomerName(string $firstName, string $lastName): self
    {
        $this->customerFirstName = $firstName;
        $this->customerLastName = $lastName;
        return $this;
    }

    public function setCustomerPhone(string $phone): self
    {
        $this->customerPhone = $phone;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setRedirectUrl(string $url): self
    {
        $this->redirectUrl = $url;
        return $this;
    }

    public function setCallbackUrl(string $url): self
    {
        $this->callbackUrl = $url;
        return $this;
    }

    public function setCancelUrl(string $url): self
    {
        $this->cancelUrl = $url;
        return $this;
    }

    public function setTest(bool $isTest): self
    {
        $this->isTest = $isTest;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function setUdfField(int $fieldNumber, string $value): self
    {
        if ($fieldNumber < 1 || $fieldNumber > 5) {
            throw new \InvalidArgumentException('UDF field number must be between 1 and 5');
        }
        $this->udfFields["udf{$fieldNumber}"] = $value;
        return $this;
    }
}