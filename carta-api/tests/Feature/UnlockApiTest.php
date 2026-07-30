<?php

namespace Tests\Feature;

use App\Models\Unlock;
use App\Support\Phone;
use App\Services\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsSender;
use Tests\TestCase;

class UnlockApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsSender();
        $this->app->instance(SmsSender::class, $this->sms);
    }

    public function test_public_phone_lookup_no_longer_exists(): void
    {
        Unlock::create(['phone' => '+258 84 000 0000', 'plan' => 'completo', 'unlocked_at' => now(), 'is_active' => true]);

        // A rota pública permitia enumerar quem tinha pago e ativar o plano
        // com o número de outra pessoa.
        $this->getJson('/api/v1/desbloqueios/840000000')->assertNotFound();
        $this->getJson('/api/v1/unlocks/840000000')->assertNotFound();
    }

    public function test_phone_normalization_is_consistent_across_formats(): void
    {
        $canonical = '258840000000';

        foreach (['840000000', '+258 84 000 0000', '258840000000', '0840000000', '00258840000000', '84-000-0000'] as $written) {
            $this->assertSame($canonical, Phone::normalize($written), "Falhou para: {$written}");
        }
    }

    public function test_unlock_requires_otp_and_binds_to_the_account(): void
    {
        [$user, $token] = $this->mobileUser(['phone' => '+258 84 000 0000']);

        Unlock::create(['phone' => '840000000', 'plan' => 'completo', 'unlocked_at' => now(), 'is_active' => true]);

        // Antes do OTP, o plano continua gratuito.
        $this->withToken($token)->getJson('/api/v1/mobile/unlock')->assertOk()->assertJsonPath('plano', 'gratis');

        $this->withToken($token)->postJson('/api/v1/mobile/unlock/request')
            ->assertOk()->assertJsonPath('estado', 'codigo_enviado');

        $code = $this->sms->lastCode();
        $this->assertNotNull($code, 'O OTP devia ter sido enviado por SMS.');

        // Código errado não ativa nada.
        $this->withToken($token)->postJson('/api/v1/mobile/unlock/confirm', ['code' => '000000'])->assertStatus(422);
        $this->assertSame('gratis', $this->withToken($token)->getJson('/api/v1/mobile/unlock')->json('plano'));

        // O código correto ativa e fica preso à conta.
        $this->withToken($token)->postJson('/api/v1/mobile/unlock/confirm', ['code' => $code])
            ->assertOk()->assertJsonPath('estado', 'ativado')->assertJsonPath('plano', 'pago');

        $this->assertDatabaseHas('unlocks', ['phone' => '840000000', 'mobile_user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_shared_phone_cannot_activate_a_second_account(): void
    {
        [, $firstToken] = $this->mobileUser(['phone' => '841111111']);
        Unlock::create(['phone' => '841111111', 'plan' => 'completo', 'unlocked_at' => now(), 'is_active' => true]);

        $this->withToken($firstToken)->postJson('/api/v1/mobile/unlock/request')->assertOk();
        $this->withToken($firstToken)->postJson('/api/v1/mobile/unlock/confirm', ['code' => $this->sms->lastCode()])->assertOk();

        // Segunda conta a usar o mesmo número: já não multiplica acessos.
        [, $secondToken] = $this->mobileUser(['phone' => '+258 84 111 1111', 'email' => 'outro@example.test']);
        $this->withToken($secondToken)->postJson('/api/v1/mobile/unlock/request')->assertStatus(409);
        $this->withToken($secondToken)->getJson('/api/v1/mobile/unlock')->assertOk()->assertJsonPath('plano', 'gratis');
    }

    public function test_unlock_without_payment_is_refused(): void
    {
        [, $token] = $this->mobileUser();

        $this->withToken($token)->postJson('/api/v1/mobile/unlock/request')->assertNotFound();
    }

    public function test_expired_unlock_stops_granting_access(): void
    {
        [$user, $token] = $this->mobileUser(['phone' => '842222222']);
        Unlock::create([
            'phone' => '842222222', 'mobile_user_id' => $user->id, 'plan' => 'completo',
            'unlocked_at' => now()->subMonths(2), 'expires_at' => now()->subDay(), 'is_active' => true,
        ]);

        $this->withToken($token)->getJson('/api/v1/mobile/unlock')->assertOk()->assertJsonPath('plano', 'gratis');

        // A tarefa agendada fecha o registo (antes `expires_at` era decorativo).
        $this->artisan('cartapro:expire-unlocks')->assertSuccessful();
        $this->assertDatabaseHas('unlocks', ['phone' => '842222222', 'is_active' => false]);
    }

}
