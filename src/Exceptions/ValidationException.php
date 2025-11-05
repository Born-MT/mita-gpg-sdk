<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Exceptions;

/**
 * Exception thrown when request validation fails (400/422)
 */
class ValidationException extends GpgException
{
    protected array $errors = [];

    public function __construct(string $message = 'Validation failed', array $errors = [], int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, ['errors' => $errors]);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}