<?php

namespace SitemapPilot\Laravel\Exceptions;

/**
 * 403 — the token is valid but belongs to a different workspace than the
 * property in the URL. Tokens are workspace-scoped; this is never retryable.
 */
class AuthorizationException extends SitemapPilotException {}
