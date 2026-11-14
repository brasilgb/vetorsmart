<?php

use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

test('a wrong password is answered in Portuguese', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    TenantUser::factory()->owner()->create(['user_id' => $user->id, 'tenant_id' => Tenant::factory()->create()->id]);

    $this->post('/login', ['email' => $user->email, 'password' => 'senha-errada'])
        ->assertSessionHasErrors(['email' => 'Essas credenciais não correspondem aos nossos registros.']);
});

test('a validation error names the field in Portuguese, not by its code name', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $request = ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => RequestStatus::Requested]);

    $this->actingAs($owner)
        ->from('/solicitacoes/'.$request->id)
        ->patch('/solicitacoes/'.$request->id.'/triagem', [])
        ->assertSessionHasErrors(['service_id' => 'O campo serviço é obrigatório.']);
});
