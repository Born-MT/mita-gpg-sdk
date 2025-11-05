<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\DTO;

use BornMT\MitaGpg\Enums\TransactionType;

/**
 * Transaction Request DTO for CAPT, REFUND, VOID operations
 */
class TransactionRequest
{
    public function __construct(
        private string $transactionId,
        private TransactionType $transactionType,
        private ?float $amount = null,
        private ?string $uniqueReference = null,
        private ?string $description = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'TransactionId' => $this->transactionId,
            'TransactionType' => $this->transactionType->value,
        ];

        if ($this->amount !== null) {
            $data['Amount'] = number_format($this->amount, 2, '.', '');
        }

        if ($this->uniqueReference !== null) {
            $data['UniqueReference'] = $this->uniqueReference;
        }

        if ($this->description !== null) {
            $data['Description'] = $this->description;
        }

        return $data;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getTransactionType(): TransactionType
    {
        return $this->transactionType;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }
}