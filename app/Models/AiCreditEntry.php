<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only ledger of AI credits. kind: monthly_grant, included_expired, consumption, purchase, refund. The unique
 * reference identifies the operation, so the same consumption, grant or purchase is never applied twice.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $kind
 * @property string $reference
 * @property int $included_delta
 * @property int $purchased_delta
 * @property int $credits
 * @property int $uncovered_credits
 * @property string|null $operation
 * @property string|null $model
 * @property int|null $tokens
 * @property int|null $estimated_cost_micros
 * @property int|null $purchase_id
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
class AiCreditEntry extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'monthly_grant' => 'Franquia do mês',
            'included_expired' => 'Franquia anterior encerrada',
            'consumption' => 'Consumo',
            'purchase' => 'Compra de créditos',
            'refund' => 'Estorno de créditos',
            default => $this->kind,
        };
    }
}
