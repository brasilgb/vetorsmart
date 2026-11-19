<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads of the RootAdmin. Streamed (rows are written as they are read, in chunks), UTF-8 with BOM and ";" as
 * separator so spreadsheets in Portuguese open it correctly. A cell that starts with =, +, -, @, tab or carriage return
 * is prefixed with an apostrophe, so a value typed by a company can never run as a formula (CSV injection).
 */
class CsvExport
{
    /**
     * @param  list<string>  $header
     * @param  callable(callable(list<scalar|null>): void): void  $rows  receives the writer and calls it once per row
     */
    public static function download(string $filename, array $header, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';', '"', '');
            $rows(function (array $row) use ($handle): void {
                fputcsv($handle, array_map(self::cell(...), $row), ';', '"', '');
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function cell(mixed $value): string
    {
        $text = $value === null ? '' : (is_bool($value) ? ($value ? 'sim' : 'não') : (string) $value);

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
