<?php

namespace SitemapPilot\Laravel\Exceptions;

/**
 * 429 — the token has spent its budget (60 requests a minute), or the address
 * has spent the looser per-origin backstop.
 *
 * The only actionable part of a rate-limit response is how long to wait, and
 * that lives in the Retry-After header rather than the body. Reading it back
 * off `retryAfter()` is the difference between a caller that can back off and
 * one that hammers the endpoint until the token is throttled again.
 */
class RateLimitException extends SitemapPilotException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        string $message,
        ?int $status = null,
        array $payload = [],
        string $body = '',
        protected ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status, $payload, $body);
    }

    /**
     * Seconds to wait before retrying, or null when the API sent no
     * Retry-After header.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
