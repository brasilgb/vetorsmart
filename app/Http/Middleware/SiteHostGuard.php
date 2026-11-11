<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a tenant's public hostname only the site, its sitemap and its robots file exist.
 * Any other path answers with a generic 404, so the administrative area is never served
 * under a company's domain.
 */
class SiteHostGuard
{
    private const array PUBLIC_PATHS = ['/', '/sitemap.xml', '/robots.txt'];

    /**
     * The visitor assistant of the site: its page and its two public endpoints. Nothing else under /atendimento.
     */
    private const array ASSISTANT_PATHS = ['/atendimento', '/atendimento/conversa', '/atendimento/mensagens'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === (string) config('platform.central_host')) {
            return $next($request);
        }

        if (in_array($request->getPathInfo(), self::ASSISTANT_PATHS, true) && in_array($request->getMethod(), ['GET', 'HEAD', 'POST'], true)) {
            return $next($request);
        }

        if (! in_array($request->getPathInfo(), self::PUBLIC_PATHS, true) || $request->isMethod('POST') || $request->isMethod('PATCH') || $request->isMethod('DELETE')) {
            abort(404);
        }

        return $next($request);
    }
}
