<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies trusted-proxy configuration from config/trusted_proxies.php at
 * request time so it stays correct under config:cache and can never be
 * bypassed by client input.
 *
 * TRUSTED_PROXIES accepts comma-separated IPs/CIDRs or "*". Empty means
 * nothing is trusted (default).
 */
final class TrustProxies
{
    private const FORWARDED_HEADERS = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX;

    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('trusted_proxies.addresses');

        if (is_string($configured) && trim($configured) !== '') {
            $proxies = trim($configured) === '*'
                ? ['*']
                : array_values(array_filter(array_map(
                    fn (string $proxy): string => trim($proxy),
                    explode(',', $configured),
                )));

            if ($proxies !== []) {
                Request::setTrustedProxies($proxies, self::FORWARDED_HEADERS);
            }
        }

        return $next($request);
    }
}
