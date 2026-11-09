<?php

namespace App\Policies;

use App\Models\Professional;
use App\Models\User;

class ProfessionalPolicy extends ServiceCatalogPolicy
{
    /**
     * Linking services to a professional is an administrative change.
     */
    public function syncServices(User $user, Professional $professional): bool
    {
        return $this->update($user, $professional);
    }
}
