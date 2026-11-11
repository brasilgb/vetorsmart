<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily consumption of the assistant per tenant (messages, provider calls and tokens).
 */
#[Fillable(['tenant_id', 'usage_date', 'messages', 'provider_calls', 'provider_failures', 'tokens'])]
class AssistantUsage extends Model
{
    use BelongsToTenant;

    protected $table = 'assistant_usage';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['usage_date' => 'date'];
    }
}
