<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link between a professional and a service they can perform.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $professional_id
 * @property int $service_id
 */
class ProfessionalService extends Pivot
{
    use BelongsToTenant;

    /**
     * @var string
     */
    protected $table = 'professional_services';

    /**
     * @var bool
     */
    public $incrementing = true;
}
