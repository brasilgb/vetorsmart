<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content Security Policy for every response, in the mode set by config/csp.php (report-only by default).
 *
 * Scripts run only from the application's own origin or with the nonce of the request: the Vite tags receive it
 * automatically and the few inline scripts of the Blade templates print it. There is no 'unsafe-inline' and no
 * 'unsafe-eval' for scripts.
 *
 * Styles keep 'unsafe-inline'. The interface sets style attributes at runtime (React components, toasts, the public
 * sites' accent color), and a nonce cannot cover an attribute; this is the one exception of the policy.
 */
class ContentSecurityPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('csp.mode', 'report-only');

        if (! in_array($mode, ['report-only', 'enforce'], true)) {
            return $next($request);
        }

        // Before the response is built, so every tag rendered for this request carries the same nonce.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $header = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';

        if (! $response->headers->has($header)) {
            $response->headers->set($header, $this->policy($nonce));
        }

        return $response;
    }

    /**
     * Scheme, host and port of the public sites (platform.public_url), the only other origin of images.
     */
    private function publicOrigin(): string
    {
        $parts = parse_url((string) config('platform.public_url'));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function policy(string $nonce): string
    {
        $directives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-{$nonce}'",
            'style-src' => "'self' 'unsafe-inline'",
            // The platform's public origin serves the published company images that the Regional Portal shows on its own host.
            'img-src' => trim("'self' data: blob: ".$this->publicOrigin()),
            'font-src' => "'self' data:",
            'connect-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
        ];

        foreach ((array) config('csp.extra', []) as $directive => $sources) {
            $sources = trim((string) $sources);

            if ($sources !== '' && isset($directives[$directive]) && ! str_contains($sources, 'unsafe-eval')) {
                $directives[$directive] .= ' '.$sources;
            }
        }

        // Absolute, on the central host: a company's own domain accepts only its site paths.
        $directives['report-uri'] = rtrim((string) config('platform.public_url'), '/').'/api/csp-report';

        return collect($directives)->map(fn (string $sources, string $directive): string => "{$directive} {$sources}")->implode('; ');
    }
}
