<?php

namespace SitemapPilot\Laravel\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Base class for every failure this package raises.
 *
 * It extends RuntimeException on purpose. Before typed exceptions existed here
 * the client threw a bare RuntimeException for every non-2xx response, and an
 * application that catches RuntimeException around a ping must keep working
 * after upgrading; narrowing the hierarchy would have been a silent breaking
 * change for exactly the callers who were handling errors properly.
 */
class SitemapPilotException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload  The decoded JSON body, when the API sent one.
     */
    public function __construct(
        string $message,
        protected ?int $status = null,
        protected array $payload = [],
        protected string $body = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /**
     * Build the most specific exception the status code justifies.
     *
     * Everything the caller could act on is carried on the exception rather
     * than flattened into the message: the status, the decoded body, and - for
     * 429 - the Retry-After header, which is the only part of a rate-limit
     * response that tells a caller what to do next and is therefore the one
     * part that must never be dropped on the floor.
     */
    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $body = $response->body();
        $payload = is_array($decoded = $response->json()) ? $decoded : [];

        $detail = is_string($payload['message'] ?? null) && $payload['message'] !== ''
            ? $payload['message']
            : ($body !== '' ? $body : 'no response body');

        $message = "SitemapPilot API request failed [{$status}]: {$detail}";

        return match (true) {
            $status === 401 => new AuthenticationException($message, $status, $payload, $body),
            $status === 403 => new AuthorizationException($message, $status, $payload, $body),
            $status === 422 => new ValidationException($message, $status, $payload, $body),
            $status === 429 => new RateLimitException(
                $message,
                $status,
                $payload,
                $body,
                retryAfter: self::parseRetryAfter($response->header('Retry-After')),
            ),
            default => new self($message, $status, $payload, $body),
        };
    }

    /**
     * The HTTP status the API answered with, or null for a transport failure.
     */
    public function status(): ?int
    {
        return $this->status;
    }

    /**
     * The decoded JSON body, or an empty array when the API sent none.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * The raw response body, exactly as received.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * RFC 9110 allows Retry-After to be either a delay in seconds or an HTTP
     * date. Laravel's ThrottleRequests sends seconds; a proxy in front of it
     * may rewrite it to a date, so both are read and both come back as a
     * number of seconds from now.
     */
    private static function parseRetryAfter(?string $header): ?int
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $header = trim($header);

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
