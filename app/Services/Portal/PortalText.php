<?php

namespace App\Services\Portal;

use Illuminate\Support\Str;

/**
 * Text normalization of the Regional Portal. Stored values and search terms go through the same steps, so accents,
 * upper and lower case, punctuation and extra spaces never change a result. The output has only a-z, 0-9 and single
 * spaces, so it is safe inside a LIKE pattern without escaping.
 */
class PortalText
{
    /**
     * Brazilian states, by abbreviation.
     *
     * @var list<string>
     */
    public const array STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    /**
     * Names of the states, for titles and breadcrumbs.
     *
     * @var array<string, string>
     */
    public const array STATE_NAMES = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    /**
     * Words that never decide a search.
     *
     * @var list<string>
     */
    private const array STOPWORDS = ['a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas', 'para', 'pra', 'por', 'com', 'um', 'uma', 'meu', 'minha', 'preciso', 'quero', 'servico', 'servicos'];

    public static function normalize(?string $text): string
    {
        $ascii = Str::lower(Str::ascii((string) $text));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $ascii)));
    }

    /**
     * Search words of a free text, at most five. A plural "s" at the end of a long word is dropped, so "instalações"
     * also finds "instalação" and "aquecedores" finds "aquecedor".
     *
     * @return list<string>
     */
    public static function tokens(?string $text): array
    {
        $tokens = [];

        foreach (explode(' ', self::normalize($text)) as $word) {
            if (strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }

            if (strlen($word) > 4 && str_ends_with($word, 'oes')) {
                $word = substr($word, 0, -3);
            } elseif (strlen($word) > 4 && (str_ends_with($word, 'res') || str_ends_with($word, 'zes'))) {
                $word = substr($word, 0, -2);
            } elseif (strlen($word) > 4 && str_ends_with($word, 's')) {
                $word = substr($word, 0, -1);
            }

            $tokens[$word] = true;
        }

        return array_slice(array_keys($tokens), 0, 5);
    }

    /**
     * Stable identifier of a municipality inside its state: the normalized name with hyphens ("sao-joao-del-rei").
     * Spellings that differ only in accents, case or punctuation share the same identifier, and the state in the
     * address keeps namesakes of different states apart.
     */
    public static function citySlug(?string $city): string
    {
        return str_replace(' ', '-', self::normalize($city));
    }

    /**
     * The normalized name behind a municipality identifier, as stored in portal_listings.city_search.
     */
    public static function cityFromSlug(string $slug): string
    {
        return str_replace('-', ' ', $slug);
    }

    public static function state(?string $state): ?string
    {
        $upper = Str::upper(trim((string) $state));

        return in_array($upper, self::STATES, true) ? $upper : null;
    }
}
