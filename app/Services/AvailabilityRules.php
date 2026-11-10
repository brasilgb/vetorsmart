<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Validates working windows: correct weekday, start before end, and no overlap on the same day.
 */
class AvailabilityRules
{
    /**
     * @param  list<array{weekday: int, start_time: string, end_time: string}>  $windows
     *
     * @throws ValidationException
     */
    public function ensureValid(array $windows): void
    {
        $byDay = [];

        foreach ($windows as $window) {
            if ($window['start_time'] >= $window['end_time']) {
                throw ValidationException::withMessages([
                    'availability' => ['O horário de término deve ser posterior ao de início.'],
                ]);
            }

            $byDay[$window['weekday']][] = $window;
        }

        foreach ($byDay as $dayWindows) {
            usort($dayWindows, fn (array $a, array $b): int => strcmp($a['start_time'], $b['start_time']));

            for ($i = 1; $i < count($dayWindows); $i++) {
                if ($dayWindows[$i]['start_time'] < $dayWindows[$i - 1]['end_time']) {
                    throw ValidationException::withMessages([
                        'availability' => ['Há janelas de trabalho sobrepostas no mesmo dia.'],
                    ]);
                }
            }
        }
    }
}
