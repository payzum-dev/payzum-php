<?php

declare(strict_types=1);

namespace Payzum;

/**
 * Placeholder for the official Payzum PHP SDK.
 *
 * This 0.0.1 release exists to reserve the package name. It intentionally
 * ships no client: publishing a half-working payment client would be worse
 * than publishing none. See the README for what v1 will contain.
 */
final class Payzum
{
    public const VERSION = '0.0.1';

    /** Production API host. `api.payzum.com` does NOT serve the API. */
    public const BASE_URL = 'https://merchant.payzum.com';

    /** Sandbox host. Isolated data, separate API keys. */
    public const SANDBOX_URL = 'https://staging.payzum.com';

    public static function isReady(): bool
    {
        return false;
    }
}
