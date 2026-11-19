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

    public static function state(?string $state): ?string
    {
        $upper = Str::upper(trim((string) $state));

        return in_array($upper, self::STATES, true) ? $upper : null;
    }
}
