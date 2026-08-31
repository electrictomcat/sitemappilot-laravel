<?php

namespace SitemapPilot\Laravel\Exceptions;

/**
 * The request never reached the API: DNS, TLS, a refused connection, or the
 * configured timeout elapsing.
 *
 * Guzzle's own exception is wrapped rather than allowed through so that a
 * caller can put one catch around this package. `status()` is null here, which
 * is how a transport failure is told apart from an API refusal: retrying is
 * reasonable, unlike a 401 or a 422.
 */
class ConnectionFailedException extends SitemapPilotException {}
