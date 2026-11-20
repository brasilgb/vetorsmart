<?php

namespace App\Services\Websites;

use Illuminate\Support\Str;

/**
 * Title and description suggested for search engines from the company's own public data: its name, the services it
 * publishes and where it is. The company can edit both; an empty field uses the suggestion when the site is published.
 * Nothing is invented: without services or a municipality the suggestion is shorter, never filled with generic claims.
 */
class SeoSuggestion
{
    public const int TITLE_MAX = 60;

    public const int DESCRIPTION_MAX = 155;

    /**
     * @param  list<string>  $services  published service names, in the site order
     * @return array{title: string, description: string}
     */
    public function suggest(string $business, ?string $segment, array $services, ?string $city, ?string $state, int $servedCount = 0): array
    {
        $business = trim($business);
        $place = $city !== null && $city !== '' ? $city.($state ? ' - '.$state : '') : null;
        $main = $services[0] ?? $segment;

        $title = $business;

        foreach (array_filter([$main !== null && $place !== null ? $main.' em '.$place : null, $place, $main]) as $candidate) {
            if (mb_strlen($business.' | '.$candidate) <= self::TITLE_MAX) {
                $title = $business.' | '.$candidate;
                break;
            }
        }

        $list = array_slice($services, 0, 3);
        $what = $list !== [] ? Str::lower(implode(', ', array_slice($list, 0, -1)).(count($list) > 1 ? ' e ' : '').end($list)) : ($segment !== null ? Str::lower($segment) : null);
        $where = $place !== null ? ' em '.$city.($servedCount > 0 ? ' e região' : '') : '';
        $description = $what !== null ? $business.': '.$what.$where.'. Solicite seu orçamento pelo site.' : $business.$where.'. Solicite seu orçamento pelo site.';

        return [
            'title' => Str::limit($title, self::TITLE_MAX, ''),
            'description' => Str::limit($description, self::DESCRIPTION_MAX - 1, '…'),
        ];
    }
}
