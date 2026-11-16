<?php

namespace App\Services\Websites;

use Illuminate\Routing\Router;

/**
 * First path segments owned by the platform. A segment slug among them would be shadowed by a platform route
 * (or a public file), so segments never receive one and the public site resolver never answers for one.
 */
class ReservedPaths
{
    /**
     * @var array<string, true>|null
     */
    private ?array $names = null;

    public function __construct(private Router $router) {}

    public function isReserved(string $slug): bool
    {
        return isset($this->names()[strtolower($slug)]);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->names());
    }

    /**
     * The configured names plus the first literal segment of every registered route.
     *
     * @return array<string, true>
     */
    private function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        $names = [];

        foreach ((array) config('platform.reserved_paths') as $name) {
            $names[strtolower((string) $name)] = true;
        }

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0];

            if ($first !== '' && ! str_starts_with($first, '{')) {
                $names[strtolower($first)] = true;
            }
        }

        return $this->names = $names;
    }
}
