<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a request's history. Append-only: no updated_at column.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $service_request_id
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $note
 * @property int|null $user_id
 */
#[Fillable(['tenant_id', 'service_request_id', 'type', 'from_status', 'to_status', 'note', 'user_id'])]
class ServiceRequestEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
