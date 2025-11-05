<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Exceptions;

/**
 * Exception thrown for general API errors (4xx, 5xx)
 */
class ApiException extends GpgException
{
    protected ?string $responseBody = null;
    protected int $statusCode;

    public function __construct(
        string $message,
        int $statusCode,
        ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous, [
            'status_code' => $statusCode,
            'response_body' => $responseBody,
        ]);

        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}