<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assistant provider
    |--------------------------------------------------------------------------
    |
    | "none" disables the provider: the deterministic flow answers every step on its own. Any other
    | value must be bound to App\Services\Assistant\AssistantProvider in a service provider. No
    | provider credential is assumed to exist; keys are read from the environment only.
    |
    */

    'provider' => env('ASSISTANT_PROVIDER', 'none'),

    'provider_api_key' => env('ASSISTANT_PROVIDER_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Limits applied to every conversation and to every tenant
    |--------------------------------------------------------------------------
    */

    'max_visitor_message_chars' => 500,

    'public_requests_per_minute' => 30,

    'max_provider_tokens_per_call' => 600,

    'max_history_messages_for_provider' => 6,

    'default_retention_days' => 30,

    /*
    | Removal of conversations past their retention date. Off by default: the daily routine only reports what has
    | expired (simulation). Turn on only after the retention policy is decided; `assistant:purge --execute` refuses
    | to run without it.
    */
    'retention_deletion_enabled' => (bool) env('ASSISTANT_RETENTION_DELETION', false),

    'session_cookie' => 'vs_assistant',

    'session_cookie_days' => 30,

];
