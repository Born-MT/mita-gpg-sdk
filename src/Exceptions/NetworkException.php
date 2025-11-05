<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Exceptions;

/**
 * Exception thrown for network/connection errors
 */
class NetworkException extends GpgException
{
    public function __construct(string $message = 'Network error occurred', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}