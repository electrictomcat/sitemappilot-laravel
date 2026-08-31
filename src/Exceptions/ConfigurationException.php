<?php

namespace SitemapPilot\Laravel\Exceptions;

/**
 * The package was called before it was configured — no API key, or no property
 * ID either configured or passed. Raised before any request is made, so
 * `status()` is null.
 */
class ConfigurationException extends SitemapPilotException {}
