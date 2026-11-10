<?php

namespace App\Services;

/**
 * Validates the company's opening hours.
 *
 * Shape: keys "0".."6" (Sunday..Saturday). Each day is null (closed) or
 * { open: "HH:MM", close: "HH:MM", breaks: [{ start: "HH:MM", end: "HH:MM" }] }.
 * Breaks must sit inside the opening window and must not overlap each other.
 */
class BusinessHoursRules
{
    /**
     * @param  array<string, mixed>  $hours
     * @return list<string> error messages; empty when valid
     */
    public function errors(array $hours): array
    {
        $errors = [];

        foreach ($hours as $weekday => $day) {
            if (! ctype_digit((string) $weekday) || (int) $weekday > 6) {
                $errors[] = 'Dia da semana inválido.';

                continue;
            }

            if ($day === null) {
                continue;
            }

            if (! is_array($day) || ! $this->isTime($day['open'] ?? null) || ! $this->isTime($day['close'] ?? null)) {
                $errors[] = 'Horário de abertura e fechamento inválidos.';

                continue;
            }

            if ($day['open'] >= $day['close']) {
                $errors[] = 'O fechamento deve ser posterior à abertura.';

                continue;
            }

            $breaks = $day['breaks'] ?? [];

            if (! is_array($breaks)) {
                $errors[] = 'Intervalos inválidos.';

                continue;
            }

            usort($breaks, fn ($a, $b): int => strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? '')));

            $previousEnd = null;

            foreach ($breaks as $break) {
                if (! is_array($break) || ! $this->isTime($break['start'] ?? null) || ! $this->isTime($break['end'] ?? null)) {
                    $errors[] = 'Intervalo com horário inválido.';

                    break;
                }

                if ($break['start'] >= $break['end'] || $break['start'] < $day['open'] || $break['end'] > $day['close']) {
                    $errors[] = 'Intervalos devem estar dentro do expediente.';

                    break;
                }

                if ($previousEnd !== null && $break['start'] < $previousEnd) {
                    $errors[] = 'Intervalos não podem se sobrepor.';

                    break;
                }

                $previousEnd = $break['end'];
            }
        }

        return array_values(array_unique($errors));
    }

    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
