<?php

namespace App\Http\Middleware;

use App\Services\Websites\WebsiteMedia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a company hostname (its own domain, or an earlier platform subdomain) only the public site exists: its home, its
 * services page, its request form, the images of its published version, its visitor assistant, its sitemap and its
 * robots file. Any other path or method answers with a generic 404, so the administrative area is never served under a
 * company's domain.
 */
class SiteHostGuard
{
    /**
     * Allowed paths and their methods on a company hostname.
     *
     * @var array<string, list<string>>
     */
    private const array SITE_PATHS = [
        '/' => ['GET', 'HEAD'],
        '/servicos' => ['GET', 'HEAD'],
        '/solicitar' => ['GET', 'HEAD', 'POST'],
        '/sitemap.xml' => ['GET', 'HEAD'],
        '/robots.txt' => ['GET', 'HEAD'],
        '/atendimento' => ['GET', 'HEAD'],
        '/atendimento/conversa' => ['POST'],
        '/atendimento/mensagens' => ['GET', 'HEAD', 'POST'],
        '/localizacao/municipios' => ['GET', 'HEAD'],
        '/localizacao/municipio' => ['POST'],
    ];

    /**
     * Fixed paths of the Regional Portal host, besides its category and region pages. Nothing else exists there: no
     * panel, no login, no company site.
     *
     * @var array<string, list<string>>
     */
    private const array PORTAL_PATHS = [
        '/' => ['GET', 'HEAD'],
        '/robots.txt' => ['GET', 'HEAD'],
        '/sitemap.xml' => ['GET', 'HEAD'],
        '/localizacao/municipios' => ['GET', 'HEAD'],
        '/localizacao/municipio' => ['POST'],
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === (string) config('platform.central_host')) {
            return $next($request);
        }

        if ($request->getHost() === (string) config('platform.portal_host')) {
            $allowed = self::PORTAL_PATHS[$request->getPathInfo()]
                ?? (preg_match('#^/(?:categorias/[a-z0-9-]+|regioes/[A-Za-z]{2}(?:/[a-z0-9-]+){0,2})$#', $request->getPathInfo()) === 1 ? ['GET', 'HEAD'] : []);

            if (! in_array($request->getMethod(), $allowed, true)) {
                abort(404);
            }

            return $next($request);
        }

        $allowed = self::SITE_PATHS[$request->getPathInfo()]
            ?? (preg_match('#^/midia/'.WebsiteMedia::FILE_PATTERN.'$#', $request->getPathInfo()) === 1 ? ['GET', 'HEAD'] : []);

        if (! in_array($request->getMethod(), $allowed, true)) {
            abort(404);
        }

        return $next($request);
    }
}
