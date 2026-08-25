<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolMembership;
use App\Models\Unlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanMonetizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_resolves_free_plus_and_school_products(): void
    {
        [$free, $freeToken] = $this->mobileUser();
        [$plus, $plusToken] = $this->mobileUser();
        [$schoolUser, $schoolToken] = $this->mobileUser();

        Unlock::create([
            'phone' => $plus->phone, 'mobile_user_id' => $plus->id, 'plan' => 'completo',
            'unlocked_at' => now(), 'is_active' => true,
        ]);
        $school = School::create(['name' => 'Escola', 'code' => 'PLAN-SCHOOL', 'is_active' => true]);
        SchoolMembership::create([
            'school_id' => $school->id, 'mobile_user_id' => $schoolUser->id,
            'status' => 'active', 'joined_at' => now(),
        ]);

        $this->withToken($freeToken)->getJson('/api/v1/mobile/unlock')->assertOk()
            ->assertJsonPath('produto', Plan::FREE)->assertJsonPath('plano', 'gratis');
        $this->withToken($plusToken)->getJson('/api/v1/mobile/unlock')->assertOk()
            ->assertJsonPath('produto', Plan::PLUS)->assertJsonPath('plano', 'pago');
        $this->withToken($schoolToken)->getJson('/api/v1/mobile/unlock')->assertOk()
            ->assertJsonPath('produto', Plan::SCHOOL)->assertJsonPath('plano', 'pago');
    }

    public function test_school_requires_an_active_membership_and_active_school(): void
    {
        [$candidate, $token] = $this->mobileUser();
        $school = School::create(['name' => 'Escola', 'code' => 'PLAN-INACTIVE', 'is_active' => false]);
        $membership = SchoolMembership::create([
            'school_id' => $school->id, 'mobile_user_id' => $candidate->id,
            'status' => 'active', 'joined_at' => now(),
        ]);

        $this->withToken($token)->getJson('/api/v1/mobile/unlock')->assertJsonPath('produto', Plan::FREE);

        $school->update(['is_active' => true]);
        $this->withToken($token)->getJson('/api/v1/mobile/unlock')->assertJsonPath('produto', Plan::SCHOOL);

        $membership->update(['status' => 'left', 'left_at' => now()]);
        $this->withToken($token)->getJson('/api/v1/mobile/unlock')->assertJsonPath('produto', Plan::FREE);
    }

    public function test_platform_admin_can_manage_catalogue_without_changing_plan_codes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plain = Str::random(80);
        ApiToken::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);
        $plus = Plan::where('code', Plan::PLUS)->firstOrFail();

        $this->withToken($plain)->getJson('/api/v1/planos')->assertOk()->assertJsonCount(3);
        $this->withToken($plain)->putJson("/api/v1/planos/{$plus->id}", [
            'price' => 199,
            'duration_days' => 120,
            'features' => ['simulados_ilimitados', 'recursos_premium'],
        ])->assertOk()->assertJsonPath('code', Plan::PLUS)->assertJsonPath('duration_days', 120);

        $this->assertDatabaseHas('plans', ['code' => Plan::PLUS, 'price' => 199, 'duration_days' => 120]);
    }

    public function test_only_plus_can_be_bought_and_legacy_complete_is_normalized(): void
    {
        config(['payments.enabled' => true, 'payments.provider' => 'fake']);
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => Plan::FREE, 'method' => 'mpesa'])
            ->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => Plan::SCHOOL, 'method' => 'mpesa'])
            ->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => Plan::LEGACY_COMPLETE, 'method' => 'mpesa'])
            ->assertCreated()->assertJsonPath('produto', Plan::PLUS);

        $this->assertDatabaseHas('payments', ['plan' => Plan::PLUS]);
        $this->assertDatabaseHas('unlocks', ['plan' => Plan::PLUS]);
    }
}
