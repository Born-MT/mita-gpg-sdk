<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Exceptions;

/**
 * Exception thrown when API authentication fails (401)
 */
class AuthenticationException extends GpgException
{
    public function __construct(string $message = 'Authentication failed. Check your API key.', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}