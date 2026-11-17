<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | mode:
    |   "off"          no CSP header;
    |   "report-only"  Content-Security-Policy-Report-Only: the browser reports what the policy would block and blocks
    |                  nothing. This is the default while the exceptions are being collected;
    |   "enforce"      Content-Security-Policy: the browser blocks. Switch only after the reports have been reviewed.
    |
    | Violations are posted by the browser to /api/csp-report on the central host and written to the application log.
    |
    */

    'mode' => env('CSP_MODE', 'report-only'),

    /*
    | Extra sources per directive, space separated, for an integration added later (for example a payment widget).
    | Keep them as narrow as the integration allows; never 'unsafe-eval'.
    */
    'extra' => [
        'script-src' => env('CSP_EXTRA_SCRIPT_SRC', ''),
        'style-src' => env('CSP_EXTRA_STYLE_SRC', ''),
        'img-src' => env('CSP_EXTRA_IMG_SRC', ''),
        'font-src' => env('CSP_EXTRA_FONT_SRC', ''),
        'connect-src' => env('CSP_EXTRA_CONNECT_SRC', ''),
    ],

];
