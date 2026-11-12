<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property PersonType $person_type
 * @property string|null $document
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $notes
 * @property CustomerStatus $status
 */
#[Fillable(['tenant_id', 'name', 'person_type', 'document', 'email', 'phone', 'whatsapp', 'address', 'city', 'state', 'notes', 'status'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Match a free-text term against name, e-mail and phone digits.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like, $digits): void {
            $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);

            if ($digits !== '') {
                $inner->orWhere('phone', 'like', '%'.$digits.'%')
                    ->orWhere('whatsapp', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'status' => CustomerStatus::class,
        ];
    }
}
