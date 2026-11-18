<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * AI credit balances of a company. Changed only through AiCreditService, under a row lock, always together with an
 * entry of the ledger (ai_credit_entries).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $included_balance
 * @property int $purchased_balance
 * @property string|null $included_period
 * @property string|null $low_balance_alerted_period
 * @property string|null $depleted_alerted_period
 */
class AiCreditAccount extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    public function totalBalance(): int
    {
        return max(0, $this->included_balance) + max(0, $this->purchased_balance);
    }
}
