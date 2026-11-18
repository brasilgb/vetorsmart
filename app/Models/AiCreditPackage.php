<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A package of additional AI credits offered for purchase. Defined by the RootAdmin; a purchase keeps a copy of the
 * credits and price, so a later change of the package never alters it.
 *
 * @property int $id
 * @property string $name
 * @property int $credits
 * @property int $price_cents
 * @property bool $active
 */
#[Fillable(['name', 'credits', 'price_cents', 'active'])]
class AiCreditPackage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
