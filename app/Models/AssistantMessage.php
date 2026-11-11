<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message of a conversation. Roles: visitor, assistant, operator, system.
 */
#[Fillable(['tenant_id', 'assistant_conversation_id', 'role', 'body', 'user_id'])]
class AssistantMessage extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<AssistantConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }
}
