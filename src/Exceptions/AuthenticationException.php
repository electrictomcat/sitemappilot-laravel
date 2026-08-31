<?php

namespace SitemapPilot\Laravel\Exceptions;

/**
 * 401 — the Bearer token was missing, wrong, or has been regenerated.
 *
 * SitemapPilot stores only a SHA-256 hash of a workspace token, so a token
 * that stops working cannot be looked up and repaired: it has to be minted
 * again on the API & SDK Integrations page.
 */
class AuthenticationException extends SitemapPilotException {}
