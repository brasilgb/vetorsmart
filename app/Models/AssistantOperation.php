<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Record of an operation that creates a request or an appointment, keyed by an idempotency key.
 */
#[Fillable(['tenant_id', 'assistant_conversation_id', 'operation', 'idempotency_key', 'result_type', 'result_id'])]
class AssistantOperation extends Model
{
    use BelongsToTenant;
}
