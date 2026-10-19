<?php

return [

    /*
     * Proxies whose X-Forwarded-For header is believed, so $request->ip() is
     * the visitor rather than the proxy. Read by Laravel's TrustProxies
     * middleware. Leave unset when the web server faces the internet
     * directly; set it when the site sits behind a CDN or load balancer
     * (Cloudflare, a hosting provider's proxy): "*" trusts whatever connects,
     * or give a comma-separated list of proxy IPs/CIDRs.
     */
    'proxies' => env('TRUSTED_PROXIES'),

];
