<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | When the application sits behind a reverse proxy / load balancer
    | (Nginx, Apache, Cloudflare, Laravel Cloud...), list the proxies allowed
    | to set X-Forwarded-* headers so correct client IPs and HTTPS detection
    | work. Comma-separated IPs/CIDRs through TRUSTED_PROXIES, or "*" only
    | for platforms that require it. Empty by default: trust nothing.
    |
    */

    'addresses' => env('TRUSTED_PROXIES'),

];
