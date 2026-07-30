<?php

namespace Tests;

use App\Models\MobileApiToken;
use App\Models\MobileUser;
use App\Models\Unlock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** Cria uma conta móvel e devolve [utilizador, token em claro]. */
    protected function mobileUser(array $attributes = []): array
    {
        $user = MobileUser::create(array_merge([
            'name' => 'Aluno CartaPro',
            'email' => 'aluno'.Str::random(6).'@example.test',
            'phone' => '84'.random_int(1000000, 9999999),
            'password' => 'segredo123',
            'license_category' => 'ligeiro',
            'is_active' => true,
        ], $attributes));

        $plain = Str::random(80);
        MobileApiToken::create([
            'mobile_user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(90),
        ]);

        return [$user, $plain];
    }

    /** Conta móvel com plano pago já ativo e ligado à conta. */
    protected function paidMobileUser(array $attributes = []): array
    {
        [$user, $token] = $this->mobileUser($attributes);

        Unlock::create([
            'phone' => $user->phone,
            'mobile_user_id' => $user->id,
            'plan' => 'completo',
            'unlocked_at' => now(),
            'is_active' => true,
        ]);

        return [$user, $token];
    }
}
