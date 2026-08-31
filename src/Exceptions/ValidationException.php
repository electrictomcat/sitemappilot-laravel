<?php

namespace SitemapPilot\Laravel\Exceptions;

/**
 * 422 — the request was understood and rejected: a URL that is not on the
 * property's own host, or a submit for a property with no sitemap URL yet.
 */
class ValidationException extends SitemapPilotException
{
    /**
     * The per-field errors Laravel's validator returns, keyed by field.
     *
     * `urls.0` is the common one: IndexNow only accepts URLs on the property's
     * own host, and the API rejects the whole batch rather than quietly
     * dropping the strays, so this names which element was wrong.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        $errors = $this->payload['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }
}
