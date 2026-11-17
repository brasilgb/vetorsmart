<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives the violation reports browsers send for the Content Security Policy and writes one log line for each.
 *
 * Anyone can post here, so the body is treated as untrusted: it is size limited, only the known fields are kept,
 * each one is truncated, and query strings are dropped from the addresses so no token or personal data reaches the log.
 */
class CspReportController extends Controller
{
    private const int MAX_BODY_BYTES = 8192;

    public function __invoke(Request $request): Response
    {
        $raw = (string) $request->getContent();

        if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        $decoded = json_decode($raw, true);
        $report = is_array($decoded) ? ($decoded['csp-report'] ?? $decoded[0]['body'] ?? null) : null;

        if (! is_array($report)) {
            return response()->noContent();
        }

        Log::warning('csp-violation', [
            'directive' => $this->text($report['effective-directive'] ?? $report['effectiveDirective'] ?? $report['violated-directive'] ?? null),
            'blocked' => $this->address($report['blocked-uri'] ?? $report['blockedURL'] ?? null),
            'document' => $this->address($report['document-uri'] ?? $report['documentURL'] ?? null),
            'source' => $this->address($report['source-file'] ?? $report['sourceFile'] ?? null),
            'line' => is_numeric($report['line-number'] ?? $report['lineNumber'] ?? null) ? (int) ($report['line-number'] ?? $report['lineNumber']) : null,
            'disposition' => $this->text($report['disposition'] ?? null),
        ]);

        return response()->noContent();
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) ? Str::limit(preg_replace('/[^\x20-\x7E]/', '', $value) ?? '', 120, '') : null;
    }

    /**
     * An address without its query string and fragment. Keywords such as "inline" and "eval" pass as they are.
     */
    private function address(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : Str::limit(preg_replace('/[?#].*$/', '', $text) ?? '', 200, '');
    }
}
