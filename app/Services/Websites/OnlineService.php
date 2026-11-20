<?php

namespace App\Services\Websites;

use App\Models\AssistantSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * What the visitor assistant of a company can really do, so the site never promises more: "Agendar online" only when
 * the assistant is on and at least one active service has an active professional with availability (the assistant
 * then offers real free times); "Atendimento online" when the assistant is on but cannot schedule; nothing otherwise.
 */
class OnlineService
{
    /**
     * @return 'schedule'|'chat'|null
     */
    public function mode(Tenant $tenant): ?string
    {
        if (! (bool) AssistantSetting::query()->forTenant($tenant)->value('enabled')) {
            return null;
        }

        $bookable = DB::table('professional_services')
            ->join('services', 'services.id', '=', 'professional_services.service_id')
            ->join('professionals', 'professionals.id', '=', 'professional_services.professional_id')
            ->where('professional_services.tenant_id', $tenant->getKey())
            ->where('services.active', true)
            ->where('professionals.active', true)
            ->whereExists(fn ($availability) => $availability->selectRaw('1')
                ->from('professional_availabilities')
                ->whereColumn('professional_availabilities.professional_id', 'professionals.id')
                ->where('professional_availabilities.tenant_id', $tenant->getKey()))
            ->exists();

        return $bookable ? 'schedule' : 'chat';
    }
}
