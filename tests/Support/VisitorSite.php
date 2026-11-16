<?php

namespace Tests\Support;

use App\Enums\PriceType;
use App\Models\AssistantSetting;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Segment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Publishes a company site at /{segment}/{company} and talks to its visitor assistant over HTTP, the way a browser does:
 * the request goes to the company's official address and the visitor's token travels in the encrypted cookie.
 */
final class VisitorSite
{
    /**
     * A company with a published site, an active service, a professional available every day, and the assistant enabled.
     *
     * @return array{tenant: Tenant, owner: User, service: Service, professional: Professional, site: string}
     */
    public static function publish(TestCase $test, string $name, string $slug): array
    {
        $segment = Segment::query()->firstOrCreate(['slug' => 'climatizacao'], ['name' => 'Climatização', 'active' => true]);
        $tenant = Tenant::factory()->create(['name' => $name, 'slug' => $slug, 'segment_id' => $segment->id]);
        TenantSetting::factory()->create(['tenant_id' => $tenant->id, 'business_name' => $name.' Ltda', 'city' => 'Campinas', 'state' => 'SP']);

        $owner = User::factory()->create();
        TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Limpeza de ar-condicionado',
            'duration_minutes' => 60,
            'price' => 150,
            'price_type' => PriceType::Fixed,
            'active' => true,
            'sort_order' => 1,
        ]);

        $professional = Professional::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carlos Técnico', 'active' => true]);
        $professional->services()->attach($service->id, ['tenant_id' => $tenant->id]);

        foreach (range(0, 6) as $weekday) {
            ProfessionalAvailability::create([
                'tenant_id' => $tenant->id,
                'professional_id' => $professional->id,
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        AssistantSetting::create([
            'tenant_id' => $tenant->id,
            'enabled' => true,
            'display_name' => $name,
            'business_hours_text' => 'Segunda a sexta, das 8h às 18h',
            'service_region' => 'Campinas e região',
            'daily_message_limit' => 200,
            'daily_token_limit' => 100000,
            'retention_days' => 30,
        ]);

        $test->actingAs($owner)->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => $name])->assertRedirect('/meu-site');
        $test->post('/meu-site/publicar')->assertRedirect('/meu-site');
        auth()->logout();
        URL::forceRootUrl('http://localhost');

        return ['tenant' => $tenant, 'owner' => $owner, 'service' => $service, 'professional' => $professional, 'site' => 'climatizacao/'.$slug];
    }

    /**
     * Starts a visitor conversation on the company's site (segment/company). Returns the response and the visitor's plain token.
     *
     * @return array{response: TestResponse, token: ?string}
     */
    public static function start(TestCase $test, string $site): array
    {
        $response = self::withToken($test, null)->post(self::url($site, '/atendimento/conversa'), [], ['Accept' => 'application/json']);
        URL::forceRootUrl('http://localhost');

        return ['response' => $response, 'token' => self::tokenOf($response)];
    }

    /**
     * Sends one visitor message with the given token (null sends no cookie at all).
     */
    public static function say(TestCase $test, string $site, ?string $token, string $text): TestResponse
    {
        $client = self::withToken($test, $token);
        $response = $client->post(self::url($site, '/atendimento/mensagens'), ['body' => $text, 'after' => 0], ['Accept' => 'application/json']);
        URL::forceRootUrl('http://localhost');

        return $response;
    }

    /**
     * Reads the visitor's messages with the given token.
     */
    public static function read(TestCase $test, string $site, ?string $token): TestResponse
    {
        $response = self::withToken($test, $token)->get(self::url($site, '/atendimento/mensagens?after=0'), ['Accept' => 'application/json']);
        URL::forceRootUrl('http://localhost');

        return $response;
    }

    /**
     * The plain token inside the encrypted visitor cookie that the response sets, or null.
     */
    public static function tokenOf(TestResponse $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === (string) config('assistant.session_cookie')) {
                return CookieValuePrefix::remove(Crypt::decrypt($cookie->getValue(), false));
            }
        }

        return null;
    }

    /**
     * Sets the visitor cookie for the next requests of this test. Laravel keeps default cookies between requests, so a
     * missing token is sent as an empty cookie, which the server treats exactly like no cookie.
     */
    private static function withToken(TestCase $test, ?string $token): TestCase
    {
        return $test->withCookie((string) config('assistant.session_cookie'), $token ?? '');
    }

    private static function url(string $site, string $path): string
    {
        return 'http://localhost/'.$site.$path;
    }
}
