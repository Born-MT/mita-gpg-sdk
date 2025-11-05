<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Tests\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

/**
 * Mock HTTP Client Builder for Testing
 */
class MockHttpClient
{
    private array $responses = [];

    /**
     * Add a successful JSON response
     */
    public function addJsonResponse(array $data, int $statusCode = 200): self
    {
        $this->responses[] = new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );

        return $this;
    }

    /**
     * Add an error response
     */
    public function addErrorResponse(int $statusCode, array $data = []): self
    {
        $this->responses[] = new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );

        return $this;
    }

    /**
     * Add a network exception
     */
    public function addNetworkException(string $message = 'Network error'): self
    {
        $this->responses[] = new RequestException(
            $message,
            new Request('GET', 'test'),
            null
        );

        return $this;
    }

    /**
     * Add multiple responses at once
     */
    public function addResponses(array $responses): self
    {
        foreach ($responses as $response) {
            if ($response instanceof Response || $response instanceof \Exception) {
                $this->responses[] = $response;
            } elseif (is_array($response)) {
                $this->addJsonResponse($response);
            }
        }

        return $this;
    }

    /**
     * Build and return the mock Guzzle client
     */
    public function build(): Client
    {
        $mock = new MockHandler($this->responses);
        $handlerStack = HandlerStack::create($mock);

        return new Client([
            'handler' => $handlerStack,
            'base_uri' => 'https://gpgapi.apcopay.com/api',
        ]);
    }

    /**
     * Create a client with a single response
     */
    public static function withResponse(array $data, int $statusCode = 200): Client
    {
        $builder = new self();
        $builder->addJsonResponse($data, $statusCode);
        return $builder->build();
    }

    /**
     * Create a client that returns an error
     */
    public static function withError(int $statusCode, array $data = []): Client
    {
        $builder = new self();
        $builder->addErrorResponse($statusCode, $data);
        return $builder->build();
    }

    /**
     * Create a client that throws a network exception
     */
    public static function withNetworkError(string $message = 'Network error'): Client
    {
        $builder = new self();
        $builder->addNetworkException($message);
        return $builder->build();
    }
}