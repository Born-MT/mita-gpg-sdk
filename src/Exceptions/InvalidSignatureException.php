<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Exceptions;

/**
 * Exception thrown when webhook signature verification fails
 */
class InvalidSignatureException extends GpgException
{
    public function __construct(string $message = 'Invalid webhook signature', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}