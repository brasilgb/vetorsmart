<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Presentation time zone of the platform console (RootAdmin).
 *
 * Instants are persisted in UTC. Platform-level records belong to no company, so they are shown in the
 * platform time zone instead of a tenant one. Conversion happens once, here, at presentation time.
 */
final class PlatformTime
{
    /**
     * Time zone used to present platform-level records.
     */
    public static function timezone(): string
    {
        return (string) config('platform.display_timezone', 'America/Sao_Paulo');
    }

    /**
     * Convert a persisted (UTC) instant to the platform time zone.
     */
    public static function toDisplay(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::timezone());
    }

    /**
     * Date and time of an instant, as shown in the console.
     */
    public static function dateTime(?CarbonInterface $instant): ?string
    {
        return $instant === null ? null : self::toDisplay($instant)->format('d/m/Y H:i');
    }

    /**
     * Calendar date of an instant, as shown in the console.
     */
    public static function date(?CarbonInterface $instant): ?string
    {
        return $instant === null ? null : self::toDisplay($instant)->format('d/m/Y');
    }

    /**
     * Start of a calendar day typed in the platform time zone, as a UTC instant for querying.
     */
    public static function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->startOfDay()->utc();
    }
}
