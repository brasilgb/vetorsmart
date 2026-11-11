<?php

namespace App\Services\Assistant;

use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads what a visitor typed. It only extracts values (yes/no, option number, name, phone, date,
 * service); it never decides anything on its own. Every value it returns is checked again by the
 * flow and by the operational services before it is used.
 */
class AssistantText
{
    /** Days ahead that a visitor may book. */
    private const int MAX_DAYS_AHEAD = 60;

    public function normalize(string $text): string
    {
        return trim(Str::lower(Str::ascii($text)));
    }

    public function isYes(string $text): bool
    {
        return in_array($this->normalize($text), ['sim', 's', 'ok', 'confirmo', 'pode', 'claro', 'isso', 'yes'], true);
    }

    public function isNo(string $text): bool
    {
        return in_array($this->normalize($text), ['nao', 'n', 'cancelar', 'no'], true);
    }

    public function asksForHuman(string $text): bool
    {
        return $this->containsAny($text, ['atendente', 'humano', 'operador', 'pessoa real', 'falar com alguem', 'falar com uma pessoa']);
    }

    public function asksForStatus(string $text): bool
    {
        return $this->containsAny($text, ['status', 'situacao', 'meu agendamento', 'andamento']);
    }

    public function asksForPrice(string $text): bool
    {
        return $this->containsAny($text, ['preco', 'valor', 'quanto custa', 'custa', 'quanto fica', 'orcamento']);
    }

    public function asksForHours(string $text): bool
    {
        return $this->containsAny($text, ['horario', 'funciona', 'abre', 'fecha']);
    }

    public function asksForRegion(string $text): bool
    {
        return $this->containsAny($text, ['endereco', 'regiao', 'atende em', 'onde fica', 'bairro', 'cidade']);
    }

    public function asksForAnotherDate(string $text): bool
    {
        return $this->containsAny($text, ['outra data', 'outro dia', 'outra dia', 'mudar a data', 'outra hora']);
    }

    /**
     * The number of an option, for example "2". Null when the text is not only a number.
     */
    public function optionNumber(string $text): ?int
    {
        return preg_match('/^\s*(\d{1,2})\s*$/', $text, $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * A Brazilian phone number as digits (DDD + number, optionally with country code 55).
     */
    public function phone(string $text): ?string
    {
        if (preg_match_all('/\+?\d[\d\s().-]{7,}\d/', $text, $matches) === false) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';

            if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
                $digits = substr($digits, 2);
            }

            if (strlen($digits) >= 10 && strlen($digits) <= 11) {
                return $digits;
            }
        }

        return null;
    }

    /**
     * The name typed by the visitor: letters only, after the phone number has been removed.
     */
    public function name(string $text): ?string
    {
        $withoutPhone = preg_replace('/\+?\d[\d\s().-]{7,}\d/', ' ', $text) ?? $text;
        $letters = trim(preg_replace('/[^\p{L}\s\'-]+/u', ' ', $withoutPhone) ?? '');
        $letters = trim(preg_replace('/\s+/', ' ', $letters) ?? '');

        if (mb_strlen($letters) < 2 || mb_strlen($letters) > 120) {
            return null;
        }

        if ($this->isYes($letters) || $this->isNo($letters)) {
            return null;
        }

        if (in_array($this->normalize($letters), ['oi', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'obrigado', 'obrigada'], true)) {
            return null;
        }

        return $letters;
    }

    /**
     * A date from "hoje", "amanhã" or "dd/mm" (or "dd/mm/yyyy"), in the tenant's timezone.
     * Returns null when the text has no date, the date does not exist, or it is out of range.
     */
    public function date(string $text, Tenant $tenant): ?CarbonImmutable
    {
        $normalized = $this->normalize($text);
        $today = CarbonImmutable::now($tenant->timezone)->startOfDay();

        if (str_contains($normalized, 'hoje')) {
            return $tenant->startOfLocalDay($today->toDateString());
        }

        if (str_contains($normalized, 'amanha')) {
            return $tenant->startOfLocalDay($today->addDay()->toDateString());
        }

        if (preg_match('/(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?/', $normalized, $match) !== 1) {
            return null;
        }

        $year = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : (int) $today->year;
        $month = (int) $match[2];
        $day = (int) $match[1];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = $tenant->startOfLocalDay(sprintf('%04d-%02d-%02d', $year, $month, $day));

        if ($date->lessThan($today) || $date->greaterThan($today->addDays(self::MAX_DAYS_AHEAD))) {
            return null;
        }

        return $date;
    }

    /**
     * The active service the text refers to. Null when none matches or when two match equally well.
     *
     * @param  Collection<int, Service>  $services
     */
    public function matchService(string $text, Collection $services): ?Service
    {
        $words = $this->words($text);
        $best = null;
        $bestScore = 0;
        $tie = false;

        foreach ($services as $service) {
            $score = count(array_intersect($words, $this->words($service->name)));

            if ($score > $bestScore) {
                $best = $service;
                $bestScore = $score;
                $tie = false;
            } elseif ($score > 0 && $score === $bestScore) {
                $tie = true;
            }
        }

        return $tie ? null : $best;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', $this->normalize($text)) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word): bool => strlen($word) >= 3)));
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        $normalized = ' '.$this->normalize($text).' ';

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
